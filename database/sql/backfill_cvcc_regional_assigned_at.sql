-- Backfill meta.regional_assigned_at untuk case CVCC yang sudah punya regional_user_ids
-- tapi belum ada timestamp (data sebelum fitur regional_assigned_at).
-- Pakai updated_at sebagai perkiraan — tidak seakurat assign pertama yang tercatat baru.
-- Jalankan sekali setelah deploy kode terbaru.

UPDATE `feedback_cases`
SET `meta` = JSON_SET(
    COALESCE(`meta`, JSON_OBJECT()),
    '$.regional_assigned_at',
    DATE_FORMAT(`updated_at`, '%Y-%m-%d %H:%i:%s')
)
WHERE `meta` IS NOT NULL
  AND JSON_LENGTH(COALESCE(JSON_EXTRACT(`meta`, '$.regional_user_ids'), JSON_ARRAY())) > 0
  AND (
      JSON_EXTRACT(`meta`, '$.regional_assigned_at') IS NULL
      OR JSON_UNQUOTE(JSON_EXTRACT(`meta`, '$.regional_assigned_at')) = ''
  );
