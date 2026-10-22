<?php

namespace App\Enums;

/**
 * Every account belongs to an administrator: there is no public registration.
 * Both roles work with price uploads; only the super admin manages the other admins.
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
}
