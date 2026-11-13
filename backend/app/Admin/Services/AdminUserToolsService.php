<?php

namespace App\Admin\Services;

use App\Admin\DTOs\Requests\AddUserNoteData;
use App\Admin\DTOs\Requests\AdminAuditQuery;
use App\Admin\DTOs\Requests\BulkSuspendData;
use App\Admin\DTOs\Requests\UserNoteQuery;
use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\DTOs\Responses\AdminUserDetailResponse;
use App\Admin\DTOs\Responses\AdminUserNoteResponse;
use App\Admin\DTOs\Responses\BulkSuspendResponse;
use App\Admin\Exceptions\CannotModerateSelf;
use App\Admin\Exceptions\CannotModerateStaff;
use App\Admin\Exceptions\MemberAlreadyVerified;
use App\Admin\Exceptions\NoteNotFound;
use App\Admin\Exceptions\NothingToRemove;
use App\Admin\Exceptions\NotYourNote;
use App\Admin\Exceptions\ResendLimitReached;
use App\Admin\InterfaceRepository\AdminUserNoteRepositoryInterface;
use App\Admin\InterfaceRepository\AdminUserRepositoryInterface;
use App\Admin\InterfaceServices\AdminAuditServiceInterface;
use App\Admin\InterfaceServices\AdminServiceInterface;
use App\Admin\InterfaceServices\AdminUserToolsServiceInterface;
use App\Admin\Mail\AccountReinstatedMail;
use App\Admin\Support\AdminAction;
use App\Admin\Support\AdminSubjectType;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Auth\InterfaceServices\EmailVerificationServiceInterface;
use App\Auth\InterfaceServices\SocialAuthServiceInterface;
use App\Models\AdminUserNote;
use App\Models\User;
use App\MovieList\InterfaceServices\MovieListServiceInterface;
use App\Notification\InterfaceServices\NotificationServiceInterface;
use App\Report\InterfaceServices\ReportServiceInterface;
use App\Review\InterfaceServices\ReviewServiceInterface;
use App\Room\InterfaceServices\RoomServiceInterface;
use App\Support\SendsMailAfterResponse;
use App\User\InterfaceServices\AccountServiceInterface;
use App\User\InterfaceServices\ProfileServiceInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class AdminUserToolsService implements AdminUserToolsServiceInterface
{
    use SendsMailAfterResponse;

    /** How many audit entries the member page shows. */
    private const RECENT_AUDIT = 10;

    /** Resends allowed per member each minute, the count the public resend route allows per address. */
    private const RESENDS_PER_MINUTE = 3;

    /** Prefix of the rate-limiter key of one member's resends, counted whoever asks, once the staff member's right to act is checked. */
    private const RESEND_KEY = 'admin-resend-verification:';

    /** How many expired suspensions are looked up at a time. */
    private const LIFT_CHUNK = 100;

    public function __construct(
        private readonly AdminUserRepositoryInterface $users,
        private readonly AdminUserNoteRepositoryInterface $notes,
        private readonly AdminServiceInterface $admin,
        private readonly AdminAuditServiceInterface $audit,
        private readonly AuthServiceInterface $auth,
        private readonly SocialAuthServiceInterface $social,
        private readonly EmailVerificationServiceInterface $verification,
        private readonly ReviewServiceInterface $reviews,
        private readonly MovieListServiceInterface $lists,
        private readonly RoomServiceInterface $rooms,
        private readonly ProfileServiceInterface $profiles,
        private readonly ReportServiceInterface $reports,
        private readonly AccountServiceInterface $accounts,
        private readonly NotificationServiceInterface $notifications,
    ) {}

    public function detail(User $viewer, User $target): AdminUserDetailResponse
    {
        $written = $this->reviews->countsForUser($target);
        $follows = $this->profiles->followCounts($target);
        $reports = $this->reports->countsForMember($target);

        $recentAudit = $viewer->role === 'admin'
            ? $this->audit->list(new AdminAuditQuery(subjectType: AdminSubjectType::User, subjectId: $target->id, perPage: self::RECENT_AUDIT))->items
            : null;

        return AdminUserDetailResponse::fromModel(
            $target,
            [
                'reviews_with_text' => $written['reviews_with_text'],
                'ratings' => $written['ratings'],
                'lists' => $this->lists->countOwnedBy($target),
                'followers' => $follows['followers'],
                'following' => $follows['following'],
                'rooms_created' => $this->rooms->countCreatedBy($target),
                'reports_against_open' => $reports['against_open'],
                'reports_filed' => $reports['filed'],
            ],
            $this->social->linkedProviders($target),
            $this->auth->activeSessionCount($target),
            $this->notes->countFor($target->id),
            $recentAudit,
        );
    }

    public function notes(User $viewer, User $target, UserNoteQuery $query): AdminPageResponse
    {
        $this->assertMayAct($viewer, $target);
        $paginator = $this->notes->paginate($target->id, $query);

        return new AdminPageResponse(
            array_map(fn (AdminUserNote $note): AdminUserNoteResponse => AdminUserNoteResponse::fromModel($note), $paginator->items()),
            $paginator->currentPage(),
            $paginator->lastPage(),
            $paginator->perPage(),
            $paginator->total(),
        );
    }

    public function addNote(User $author, User $target, AddUserNoteData $data): AdminUserNoteResponse
    {
        $this->assertMayAct($author, $target);

        $note = DB::transaction(function () use ($author, $target, $data): AdminUserNote {
            $member = $this->users->lockForUpdate($target);
            $note = $this->notes->create($member->id, $author->id, $data->body);
            $this->audit->record($author, AdminAction::UserNoteAdded, $member->id, $this->userLabel($member));

            return $note;
        });

        return AdminUserNoteResponse::fromModel($note);
    }

    public function deleteNote(User $actor, User $target, AdminUserNote $note): void
    {
        $this->assertMayAct($actor, $target);

        if ($note->user_id !== $target->id) {
            throw new NoteNotFound;
        }

        if ($actor->role !== 'admin' && $note->author_id !== $actor->id) {
            throw new NotYourNote;
        }

        DB::transaction(function () use ($actor, $target, $note): void {
            if (! $this->notes->delete($note)) {
                throw new NoteNotFound;
            }

            $this->audit->record($actor, AdminAction::UserNoteDeleted, $target->id, $this->userLabel($target));
        });
    }

    public function signOut(User $actor, User $target): void
    {
        $this->assertMayAct($actor, $target);

        DB::transaction(function () use ($actor, $target): void {
            $member = $this->users->lockForUpdate($target);
            $this->audit->record($actor, AdminAction::UserSignedOut, $member->id, $this->userLabel($member));
            $this->auth->revokeSessions($member);
        });
    }

    public function removeAvatar(User $actor, User $target): void
    {
        $this->assertMayAct($actor, $target);

        $member = DB::transaction(function () use ($actor, $target): User {
            $member = $this->users->lockForUpdate($target);

            if ($member->avatar === null) {
                throw NothingToRemove::noAvatar();
            }

            $this->accounts->removeAvatar($member);
            $this->audit->record($actor, AdminAction::UserAvatarRemoved, $member->id, $this->userLabel($member));

            return $member;
        });

        $this->tellMemberProfileChanged($member, 'avatar_removed');
    }

    public function removeBio(User $actor, User $target): void
    {
        $this->assertMayAct($actor, $target);

        $member = DB::transaction(function () use ($actor, $target): User {
            $member = $this->users->lockForUpdate($target);

            if (blank($member->bio)) {
                throw NothingToRemove::noBio();
            }

            $this->accounts->clearBio($member);
            $this->audit->record($actor, AdminAction::UserBioRemoved, $member->id, $this->userLabel($member));

            return $member;
        });

        $this->tellMemberProfileChanged($member, 'bio_removed');
    }

    public function resendVerification(User $actor, User $target): void
    {
        $this->assertMayAct($actor, $target);

        $member = DB::transaction(function () use ($actor, $target): User {
            $member = $this->users->lockForUpdate($target);

            if ($member->email_verified_at !== null) {
                throw new MemberAlreadyVerified;
            }

            $limit = self::RESEND_KEY.$member->id;

            if (RateLimiter::tooManyAttempts($limit, self::RESENDS_PER_MINUTE)) {
                throw new ResendLimitReached(RateLimiter::availableIn($limit));
            }

            $this->audit->record($actor, AdminAction::UserVerificationResent, $member->id, $this->userLabel($member));
            RateLimiter::hit($limit, 60);

            return $member;
        });

        $this->verification->send($member);
    }

    public function bulkSuspend(User $actor, BulkSuspendData $data): BulkSuspendResponse
    {
        $targets = $this->users->findMany($data->userIds)->keyBy('id');
        $results = [];

        foreach ($data->userIds as $id) {
            $target = $targets->get($id);

            $results[] = $target === null
                ? ['id' => $id, 'status' => 'skipped', 'reason' => 'not_found']
                : ['id' => $id, ...$this->suspendOne($actor, $target, $data)];
        }

        return new BulkSuspendResponse($results);
    }

    /**
     * @return array{status: 'suspended'|'skipped', reason: 'already_suspended'|'not_allowed'|'self'|'not_found'|'failed'|null}
     */
    private function suspendOne(User $actor, User $target, BulkSuspendData $data): array
    {
        try {
            $suspended = $this->admin->suspendUser($actor, $target, $data->reason, $data->suspendedUntil);
        } catch (CannotModerateSelf) {
            return ['status' => 'skipped', 'reason' => 'self'];
        } catch (CannotModerateStaff) {
            return ['status' => 'skipped', 'reason' => 'not_allowed'];
        } catch (ModelNotFoundException) {
            return ['status' => 'skipped', 'reason' => 'not_found'];
        } catch (Throwable $failure) {
            report($failure);

            return ['status' => 'skipped', 'reason' => 'failed'];
        }

        return $suspended ? ['status' => 'suspended', 'reason' => null] : ['status' => 'skipped', 'reason' => 'already_suspended'];
    }

    public function liftExpiredSuspensions(): int
    {
        $now = now();
        $lifted = 0;
        $afterId = 0;

        do {
            $ids = $this->users->expiredSuspensionIds($now, $afterId, self::LIFT_CHUNK);

            foreach ($ids as $id) {
                $lifted += $this->liftSuspension($id, $now) ? 1 : 0;
            }

            $afterId = $ids === [] ? $afterId : end($ids);
        } while (count($ids) === self::LIFT_CHUNK);

        if ($lifted > 0) {
            $this->admin->forgetStats();
        }

        return $lifted;
    }

    /**
     * Reinstates one member whose suspension has ended, after re-reading their row under a lock: a reinstatement by
     * staff, or by an earlier run, that committed meanwhile is seen and nothing happens twice. A failure is reported and
     * leaves the member suspended for the next run.
     */
    private function liftSuspension(int $id, CarbonInterface $now): bool
    {
        try {
            $member = DB::transaction(function () use ($id, $now): ?User {
                $current = $this->users->lockById($id);

                if ($current === null || $current->is_active || $current->suspended_until === null || $current->suspended_until->isAfter($now)) {
                    return null;
                }

                $endedAt = $current->suspended_until->toJSON();
                $lifted = $this->users->update($current, ['is_active' => true, 'suspension_reason' => null, 'suspended_until' => null]);
                $this->audit->recordAsSystem(AdminAction::UserSuspensionLifted, $lifted->id, $this->userLabel($lifted), [
                    'is_active' => ['from' => false, 'to' => true],
                    'until' => ['from' => $endedAt, 'to' => null],
                ]);

                return $lifted;
            });
        } catch (Throwable $failure) {
            report($failure);

            return false;
        }

        if ($member === null) {
            return false;
        }

        $this->mailLater($member->email, new AccountReinstatedMail($member->name));

        return true;
    }

    /**
     * Admins may act on anyone but themselves; moderators only on plain members, and never on themselves.
     *
     * @throws CannotModerateSelf
     * @throws CannotModerateStaff
     */
    private function assertMayAct(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw CannotModerateSelf::forMemberTools();
        }

        if ($actor->role !== 'admin' && $target->role !== 'user') {
            throw CannotModerateStaff::forStaffAccount();
        }
    }

    /**
     * Tells the member staff changed their profile. Called after the transaction committed; a failure to notify is
     * reported and never undoes or fails the removal.
     */
    private function tellMemberProfileChanged(User $member, string $action): void
    {
        rescue(fn () => $this->notifications->profileChanged($member, $action));
    }

    /** Usernames are what the log keeps as an account's label; emails never are. */
    private function userLabel(User $user): string
    {
        return $user->username ?? $user->name;
    }
}
