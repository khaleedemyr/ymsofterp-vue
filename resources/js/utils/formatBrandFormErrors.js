const FIELD_LABELS = {
  title: 'Judul',
  slug: 'Slug',
  link_menu: 'Link Menu',
  menu_pdf: 'Menu PDF',
  thumbnail: 'Thumbnail',
  logo_cp: 'Logo Company Profile',
  image: 'Gambar',
  content: 'Konten',
  hero_title: 'Header Title',
  hero_subtitle: 'Header Subtitle',
  hero_media: 'Header Media',
};

export function formatBrandFormErrors(validationErrors) {
  if (!validationErrors || typeof validationErrors !== 'object') {
    return [];
  }

  return Object.entries(validationErrors).flatMap(([field, messages]) => {
    const label = FIELD_LABELS[field] || field;
    const list = Array.isArray(messages) ? messages : [messages];

    return list
      .filter(Boolean)
      .map((message) => `<strong>${label}</strong>: ${message}`);
  });
}

export function brandFormErrorsHtml(validationErrors) {
  const lines = formatBrandFormErrors(validationErrors);
  if (lines.length === 0) {
    return '';
  }

  return `<ul style="text-align:left;margin:0;padding-left:1.25rem;">${lines
    .map((line) => `<li style="margin-bottom:0.35rem;">${line}</li>`)
    .join('')}</ul>`;
}
