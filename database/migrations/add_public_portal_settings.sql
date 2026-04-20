INSERT INTO system_settings (setting_key, setting_value, setting_type, description)
VALUES
('church_name', 'Bright Light Ministry Int''l', 'text', 'Public church name displayed across the donor portal'),
('portal_subtitle', 'Partners Portal', 'text', 'Short subtitle displayed in the donor portal header'),
('church_tagline', 'Together, we are advancing the gospel with every seed sown.', 'text', 'Public donor portal tagline'),
('tax_note', 'Bright Light Ministry Int''l is a registered ministry. Your gift may be tax-deductible where applicable.', 'text', 'Public giving note shown near donation actions'),
('impact_families_fed', '1247', 'number', 'Impact metric shown on the donor home page'),
('impact_missionaries_sent', '38', 'number', 'Impact metric shown on the donor home page'),
('impact_campuses_planted', '3', 'number', 'Impact metric shown on the donor home page'),
('impact_lives_touched', '12K+', 'text', 'Impact metric shown on the donor home page')
ON DUPLICATE KEY UPDATE
setting_value = VALUES(setting_value),
setting_type = VALUES(setting_type),
description = VALUES(description);
