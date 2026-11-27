<?php

namespace App\Domain\Identity\Enums;

/** Platform roles (Spatie). Every registered user implicitly has "user" permissions. */
enum Role: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Editor = 'editor';
    case Analyst = 'analyst';
    case AiManager = 'ai_manager';
    case LicenseManager = 'license_manager';
    case ProductManager = 'product_manager';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Admin => 'Administrátor',
            self::Moderator => 'Moderátor',
            self::Editor => 'Redaktor',
            self::Analyst => 'Analytik',
            self::AiManager => 'Správce AI',
            self::LicenseManager => 'Správce licencí',
            self::ProductManager => 'Správce produktů',
            self::User => 'Běžný uživatel',
        };
    }

    /**
     * Permissions per role. Superadmin gets everything through Gate::before.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Superadmin => Permission::all(),
            self::Admin => array_values(array_diff(Permission::all(), ['roles.manage'])),
            self::Moderator => ['admin.access', 'moderation.manage', 'comments.manage', 'answers.manage', 'users.view', 'users.suspend'],
            self::Editor => ['admin.access', 'questions.manage', 'daily.manage', 'categories.manage', 'styles.manage', 'quotes.manage', 'seo.manage'],
            self::Analyst => ['admin.access', 'analytics.view', 'ai.costs.view', 'needs.analytics'],
            self::AiManager => ['admin.access', 'ai.manage', 'ai.costs.view', 'prompts.manage', 'prompts.approve', 'styles.manage'],
            self::LicenseManager => ['admin.access', 'legal.manage', 'quotes.manage'],
            self::ProductManager => ['admin.access', 'products.manage'],
            self::User => [],
        };
    }
}
