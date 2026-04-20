<?php
/**
 * System settings helpers for public-facing pages.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Fetch a system setting value by key.
 */
function getSystemSetting(string $key, $default = null) {
    static $settingsCache = [];

    if (array_key_exists($key, $settingsCache)) {
        return $settingsCache[$key];
    }

    try {
        $db = Database::getInstance();
        $setting = $db->fetchOne(
            'SELECT setting_value FROM system_settings WHERE setting_key = :setting_key LIMIT 1',
            ['setting_key' => $key]
        );
    } catch (Throwable $e) {
        error_log('System setting fetch failed: ' . $e->getMessage());
        $setting = false;
    }

    $settingsCache[$key] = ($setting && array_key_exists('setting_value', $setting))
        ? $setting['setting_value']
        : $default;

    return $settingsCache[$key];
}

/**
 * Fetch multiple public portal settings with defaults.
 */
function getPortalSettings(): array {
    return [
        'church_name' => getSystemSetting('church_name', "Bright Light Ministry Int'l"),
        'portal_subtitle' => getSystemSetting('portal_subtitle', 'Partners Portal'),
        'church_tagline' => getSystemSetting(
            'church_tagline',
            'Together, we are advancing the gospel with every seed sown.'
        ),
        'tax_note' => getSystemSetting(
            'tax_note',
            "Bright Light Ministry Int'l is a registered ministry. Your gift may be tax-deductible where applicable."
        ),
        'bible_verse_text' => getSystemSetting(
            'bible_verse_text',
            '"Each of you should give what you have decided in your heart to give, not reluctantly or under compulsion, for God loves a cheerful giver."'
        ),
        'bible_verse_ref' => getSystemSetting('bible_verse_ref', '2 Corinthians 9:7'),
        'footer_privacy_url' => getSystemSetting('footer_privacy_url', '#'),
        'footer_terms_url' => getSystemSetting('footer_terms_url', '#'),
        'footer_contact_url' => getSystemSetting('footer_contact_url', '#'),
        'support_email' => getSystemSetting('support_email', 'support@brightlightministry.org'),
        'support_phone' => getSystemSetting('support_phone', '+234 800 SUPPORT'),
        'auto_resolve_days' => getSystemSetting('auto_resolve_days', '24-48'),
        'impact_families_fed' => (int) getSystemSetting('impact_families_fed', 0),
        'impact_missionaries_sent' => (int) getSystemSetting('impact_missionaries_sent', 0),
        'impact_campuses_planted' => (int) getSystemSetting('impact_campuses_planted', 0),
        'impact_lives_touched' => getSystemSetting('impact_lives_touched', '0')
    ];
}
