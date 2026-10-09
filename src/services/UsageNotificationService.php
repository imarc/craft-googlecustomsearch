<?php

namespace imarc\sitesearch\services;

use Craft;
use craft\base\Component;
use craft\elements\User;

/** Best-effort delivery using Craft's configured mail transport. */
class UsageNotificationService extends Component
{
    public function sendWarning(string $siteUid, string $month, int $threshold, int $cap): void
    {
        try {
            $siteName = $this->getSiteName($siteUid);
            $recipients = array_unique(array_filter($this->getAdminEmails()));
            if ($recipients === []) {
                $this->warn('Site Search usage email skipped: no active administrators with email addresses.');
                return;
            }

            $subject = 'Site Search usage warning';
            $body = sprintf(
                "Site Search has reached the warning threshold of %d request attempts for %s (%s) in %s (UTC).\n\n",
                $threshold, $siteName, $siteUid, $month
            );
            $body .= $cap > 0
                ? sprintf("The monthly hard cap is %d requests. Further provider calls are blocked once that cap is reached.\n", $cap)
                : "No monthly hard cap is configured; searches will continue without a request limit.\n";
            $body .= "\nUsage includes failed attempts and connection tests. Limits reset on the first day of the next month (UTC).\n"
                . "Review this site's Monthly Request Limits in the Site Search plugin settings.\n";

            // Separate messages keep addresses private and isolate delivery failures.
            foreach ($recipients as $email) {
                try {
                    if (!$this->sendEmail($email, $subject, $body)) {
                        $this->warn('Site Search usage warning email could not be sent to an administrator.');
                    }
                } catch (\Throwable) {
                    $this->warn('Site Search usage warning email failed for an administrator. Check the Craft mail configuration and logs.');
                }
            }
        } catch (\Throwable) {
            // Recipient/site lookup and mail initialization must not interrupt search.
            $this->warn('Site Search usage warning email could not be prepared. Check the Craft configuration and logs.');
        }
    }

    /** Craft administrators are installation-wide, not assigned to individual sites. */
    protected function getAdminEmails(): array
    {
        return User::find()->admin(true)->status(User::STATUS_ACTIVE)->select(['email'])->column();
    }

    protected function getSiteName(string $siteUid): string
    {
        return Craft::$app->getSites()->getSiteByUid($siteUid)->name;
    }

    protected function sendEmail(string $email, string $subject, string $body): bool
    {
        return Craft::$app->getMailer()->compose()
            ->setTo($email)
            ->setSubject($subject)
            ->setTextBody($body)
            ->send();
    }

    protected function warn(string $message): void
    {
        Craft::warning($message, 'sitesearch');
    }
}
