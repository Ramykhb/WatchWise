<?php

namespace App\MovieNight\InterfaceServices;

use App\Models\MovieNight;
use App\Models\User;
use App\MovieNight\DTOs\Requests\CreateMovieNightData;
use App\MovieNight\DTOs\Requests\InviteGuestsData;
use App\MovieNight\DTOs\Requests\RsvpData;
use App\MovieNight\DTOs\Requests\UpdateMovieNightData;
use App\MovieNight\DTOs\Requests\VoteData;
use App\MovieNight\DTOs\Responses\MovieNightOverviewResponse;
use App\MovieNight\DTOs\Responses\MovieNightResponse;
use App\MovieNight\Exceptions\DeclinedGuestCannotVote;
use App\MovieNight\Exceptions\NotInvitedToMovieNight;
use App\MovieNight\Exceptions\VotingClosed;

interface MovieNightServiceInterface
{
    /** Upcoming nights, pending invitations and past nights. */
    public function overview(User $viewer): MovieNightOverviewResponse;

    /** Creates the night, seeds the shortlist and invites the @usernames. */
    public function create(CreateMovieNightData $data, User $organizer): MovieNightResponse;

    public function show(MovieNight $night, User $viewer): MovieNightResponse;

    /** The host invites more people; users already invited and the host are skipped. */
    public function invite(MovieNight $night, InviteGuestsData $data, User $organizer): MovieNightResponse;

    /**
     * An invited guest answers.
     *
     * @throws NotInvitedToMovieNight
     */
    public function rsvp(MovieNight $night, RsvpData $data, User $guest): MovieNightResponse;

    /**
     * One vote per person; adds the film to the shortlist if it is new.
     *
     * @throws DeclinedGuestCannotVote
     * @throws VotingClosed
     */
    public function vote(MovieNight $night, VoteData $data, User $voter): MovieNightResponse;

    /** The host picks the winner, cancels or edits details. */
    public function update(MovieNight $night, UpdateMovieNightData $data, User $organizer): MovieNightResponse;
}
