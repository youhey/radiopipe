<?php

namespace App\Admin;

/**
 * 管理画面へのアクセス可否を設定ベースで判定するサービス。
 */
class AdminAccess
{
    /**
     * 指定されたメールアドレスが管理者 allow-list に含まれるか判定する。
     */
    public function isAllowedEmail(?string $email): bool
    {
        if ($email === null || trim($email) === '') {
            return false;
        }

        $normalizedEmail = $this->normalizeEmail($email);

        foreach ($this->allowedEmails() as $allowedEmail) {
            if ($normalizedEmail === $this->normalizeEmail($allowedEmail)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 設定済みの管理者メールアドレス一覧を返す。
     *
     * @return array<int, string>
     */
    public function allowedEmails(): array
    {
        $configuredEmails = config('radiopipe.admin.allowed_emails', []);

        if (! is_array($configuredEmails)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (mixed $email): string => is_string($email) ? trim($email) : '',
                $configuredEmails,
            ),
            static fn (string $email): bool => $email !== '',
        ));
    }

    /**
     * 比較用にメールアドレスを正規化する。
     */
    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
