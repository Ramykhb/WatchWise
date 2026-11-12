<?php

namespace Tests\Feature\Support;

use App\Admin\Mail\AccountReinstatedMail;
use App\Admin\Mail\AccountSuspendedMail;
use App\Auth\Mail\PasswordChangedMail;
use App\Auth\Mail\ResetPasswordMail;
use App\Auth\Mail\VerifyEmailMail;
use App\Models\User;
use App\Support\MailText;
use App\User\Mail\AccountDeletedMail;
use App\User\Mail\AuthorizeEmailChangeMail;
use App\User\Mail\ConfirmAccountDeletionMail;
use App\User\Mail\ConfirmEmailChangeMail;
use App\User\Mail\EmailChangedMail;
use App\User\Mail\EmailChangeRequestedMail;
use App\User\Mail\PasswordAddedMail;
use Closure;
use Illuminate\Mail\Mailable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The plain-text part of every account email is never rendered as HTML, so it prints links and names as they are:
 * an escaped `&amp;` would break a reset link (`?token=..&email=..`) or a signed link's query string.
 */
class PlainTextMailTest extends TestCase
{
    private const NAME = "Tom & O'Brien";

    private const LINK = 'https://api.test/api/auth/link/7/abc?expires=1700000000&signature=a1b2&email=tom%40example.com';

    private const CANCEL = 'https://api.test/api/me/cancel/7/def?expires=1700000000&signature=c3d4';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.frontend_url' => 'https://front.test']);
    }

    /** @return array<string, array{Closure(string): Mailable, list<string>}> */
    public static function mails(): array
    {
        return [
            'verify email' => [fn (string $name): Mailable => new VerifyEmailMail(new User(['name' => $name]), self::LINK), [self::LINK]],
            'reset password' => [fn (string $name): Mailable => new ResetPasswordMail($name, self::LINK), [self::LINK]],
            'password changed' => [fn (string $name): Mailable => new PasswordChangedMail($name), ['https://front.test/forgot-password']],
            'password added' => [fn (string $name): Mailable => new PasswordAddedMail($name), ['https://front.test/forgot-password']],
            'confirm email change' => [fn (string $name): Mailable => new ConfirmEmailChangeMail($name, self::LINK), [self::LINK]],
            'authorize email change' => [fn (string $name): Mailable => new AuthorizeEmailChangeMail($name, 't***@example.com', self::LINK, self::CANCEL), [self::LINK, self::CANCEL]],
            'email change requested' => [fn (string $name): Mailable => new EmailChangeRequestedMail($name, 't***@example.com', self::CANCEL), [self::CANCEL, 'https://front.test/edit-profile']],
            'email changed' => [fn (string $name): Mailable => new EmailChangedMail($name, 't***@example.com'), ['https://front.test/signin']],
            'confirm account deletion' => [fn (string $name): Mailable => new ConfirmAccountDeletionMail($name, self::LINK, self::CANCEL), [self::LINK, self::CANCEL]],
            'account deleted' => [fn (string $name): Mailable => new AccountDeletedMail($name), ['https://front.test/signup']],
            'account suspended' => [fn (string $name): Mailable => new AccountSuspendedMail($name), []],
            'account reinstated' => [fn (string $name): Mailable => new AccountReinstatedMail($name), ['https://front.test/signin']],
        ];
    }

    private function text(Mailable $mail): string
    {
        return (fn (): array => $this->renderForAssertions())->call($mail)[1];
    }

    /**
     * @param  Closure(string): Mailable  $make
     * @param  list<string>  $links
     */
    #[DataProvider('mails')]
    public function test_the_text_part_prints_links_and_the_name_unescaped(Closure $make, array $links): void
    {
        $text = $this->text($make(self::NAME));

        foreach ($links as $link) {
            $this->assertStringContainsString($link, $text);
        }
        $this->assertStringContainsString('Hi '.self::NAME, $text);
        $this->assertStringNotContainsString('&amp;', $text);
        $this->assertStringNotContainsString('&#039;', $text);
    }

    /** @param  Closure(string): Mailable  $make */
    #[DataProvider('mails')]
    public function test_control_characters_in_a_name_never_reach_either_part(Closure $make): void
    {
        $mail = $make("Tom\u{202E}gnp.exe\r\nClick https://evil.test\u{0000}");
        [$html, $text] = (fn (): array => $this->renderForAssertions())->call($mail);

        $this->assertStringContainsString('Hi Tom gnp.exe Click https://evil.test', $text);
        foreach ([$html, $text] as $part) {
            $this->assertStringNotContainsString("\u{202E}", $part);
            $this->assertStringNotContainsString("\u{0000}", $part);
        }
        $this->assertStringNotContainsString("Tom\u{202E}", $text);
        $this->assertStringNotContainsString("gnp.exe\r\n", $text);
    }

    public function test_emoji_joined_by_zwj_are_printed_whole(): void
    {
        $family = "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467} Family";

        $this->assertStringContainsString('Hi '.$family, $this->text(new PasswordChangedMail($family)));
        $this->assertSame('Tom Evil', MailText::line("Tom\u{2066}\u{202E}\nEvil\u{FEFF}"));
    }
}
