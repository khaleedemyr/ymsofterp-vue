/**
 * Koreksi ejaan deterministik chat Indonesia (mirror OmniChatSpellfix.php).
 * Dipakai di composer web sebelum/sesudah AI agar modal typo tetap muncul.
 */
const REPLACEMENTS = [
  [/\bape\b/giu, 'apa'],
  [/\bapae\b/giu, 'apa'],
  [/\bapaa\b/giu, 'apa'],
  [/\bgmn\b/giu, 'gimana'],
  [/\bgimna\b/giu, 'gimana'],
  [/\bknp\b/giu, 'kenapa'],
  [/\bknpa\b/giu, 'kenapa'],
  [/\bblm\b/giu, 'belum'],
  [/\budh\b/giu, 'sudah'],
  [/\budah\b/giu, 'sudah'],
  [/\btlg\b/giu, 'tolong'],
  [/\btolongnya\b/giu, 'tolong'],
  [/\bmksd\b/giu, 'maksud'],
  [/\bbgt\b/giu, 'banget'],
  [/\btrims\b/giu, 'terima kasih'],
  [/\bmakasih\b/giu, 'terima kasih'],
  [/\bmaafin\b/giu, 'maaf'],
  [/\bsy\b/giu, 'saya'],
  [/\bgk\b/giu, 'nggak'],
  [/\bga\b/giu, 'nggak'],
  [/\bdongh\b/giu, 'dong'],
  [/\bsiapah\b/giu, 'siapa'],
]

function preserveTrailingPunctuation(original, fixed) {
  const m = original.match(/([?!.…]+)\s*$/u)
  if (!m) return fixed
  if (/[?!.…]+\s*$/u.test(fixed)) return fixed
  return fixed.trimEnd() + m[1]
}

export function applyOmniChatSpellfix(text) {
  let out = String(text)
  for (const [pattern, replacement] of REPLACEMENTS) {
    out = out.replace(pattern, replacement)
  }
  return preserveTrailingPunctuation(text, out)
}
