<?php

namespace App\Support;

final class Roles
{
    public const SUPER_ADMIN = 'SUPER_ADMIN';
    public const ADMIN = 'ADMIN';
    public const EXAMINER = 'EXAMINER';

    public static function normalize(?string $role): string
    {
        return strtoupper((string) $role);
    }

    public static function isAdminLike(?string $role): bool
    {
        return in_array(self::normalize($role), [self::SUPER_ADMIN, self::ADMIN], true);
    }

    public static function isSuperAdmin(?string $role): bool
    {
        return self::normalize($role) === self::SUPER_ADMIN;
    }

    public static function isExaminer(?string $role): bool
    {
        return self::normalize($role) === self::EXAMINER;
    }

    public static function canManageSettings(?string $role): bool
    {
        return self::isSuperAdmin($role);
    }

    public static function canManageRoles(?string $role): bool
    {
        return self::isSuperAdmin($role);
    }

    public static function canManageFees(?string $role): bool
    {
        return self::isSuperAdmin($role);
    }

    public static function canManageSessions(?string $role): bool
    {
        return self::isSuperAdmin($role);
    }

    public static function canManageExaminers(?string $role): bool
    {
        return self::isAdminLike($role);
    }

    public static function canManageMaintenance(?string $role): bool
    {
        return self::isSuperAdmin($role);
    }
}
