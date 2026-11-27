<?php

namespace App\Domain\Identity\Enums;

final class Permission
{
    public const LABELS = [
        'admin.access' => 'Přístup do administrace',
        'users.view' => 'Zobrazit uživatele',
        'users.manage' => 'Spravovat uživatele',
        'users.suspend' => 'Pozastavit uživatele',
        'roles.manage' => 'Spravovat role a oprávnění',
        'questions.manage' => 'Spravovat otázky',
        'answers.manage' => 'Spravovat odpovědi',
        'comments.manage' => 'Spravovat komentáře',
        'categories.manage' => 'Spravovat kategorie',
        'daily.manage' => 'Plánovat otázku dne',
        'styles.manage' => 'Spravovat jazykové osobnosti',
        'reactions.manage' => 'Spravovat reakce',
        'moderation.manage' => 'Moderovat obsah',
        'rankings.manage' => 'Spravovat žebříčky a ocenění',
        'needs.manage' => 'Spravovat potřeby',
        'needs.analytics' => 'Analytika potřeb a příležitostí',
        'ai.manage' => 'Spravovat AI poskytovatele a modely',
        'ai.costs.view' => 'Zobrazit náklady AI',
        'prompts.manage' => 'Upravovat prompty',
        'prompts.approve' => 'Schvalovat prompty do produkce',
        'legal.manage' => 'Právní evidence a licence',
        'quotes.manage' => 'Spravovat hlášky',
        'products.manage' => 'Spravovat produkty',
        'features.manage' => 'Feature flags',
        'settings.manage' => 'Systémová nastavení',
        'seo.manage' => 'SEO',
        'analytics.view' => 'Statistiky',
        'audit.view' => 'Auditní log',
        'privacy.manage' => 'Žádosti GDPR',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }
}
