/** Emoji umum untuk inbox / template (UTF-8; DB omni_* utf8mb4). */
export const omniEmojiPickerList = [
  '😀', '😃', '😄', '😁', '😊', '🙂', '😉', '😍', '🥰', '😘', '😗', '😋', '😛', '😜', '🤪', '😝',
  '🤗', '🤔', '😐', '😑', '😶', '🙄', '😏', '😣', '😥', '😮', '🤐', '😯', '😪', '😫', '🥱', '😴',
  '😌', '🤤', '😒', '😓', '😔', '😕', '🙃', '🤑', '😲', '☹️', '🙁', '😖', '😞', '😟', '😤',
  '😢', '😭', '😦', '😧', '😨', '😩', '🤯', '😬', '😰', '😱', '🥵', '🥶', '😳', '😵', '🥴',
  '👍', '👎', '👌', '✌️', '🤞', '🤝', '🙏', '💪', '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '💯',
  '✅', '❌', '⭐', '🔥', '🎉', '🎊', '💐', '🌹', '🙌', '👏', '💬', '📱', '📞', '📍', '🕐',
]

/**
 * @param {HTMLTextAreaElement | null} el
 * @param {import('vue').Ref<string>} textRef
 * @param {string} emoji
 * @param {() => void} [afterInsert]
 */
export function insertEmojiIntoTextarea(el, textRef, emoji, afterInsert) {
  const text = textRef.value ?? ''
  if (!el) {
    textRef.value = text + emoji
    afterInsert?.()
    return
  }
  const start = el.selectionStart ?? text.length
  const end = el.selectionEnd ?? start
  textRef.value = text.slice(0, start) + emoji + text.slice(end)
  afterInsert?.()
  requestAnimationFrame(() => {
    el.focus()
    const pos = start + emoji.length
    el.setSelectionRange(pos, pos)
  })
}
