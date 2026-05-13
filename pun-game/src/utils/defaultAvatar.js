/** 缺省头像，与首页 `index-badge.jpg` 一致 */
export const DEFAULT_AVATAR_URL =
  'https://sofun.online/static/mini/index-badge.jpg'

/**
 * 排行榜等：昵称按 Unicode 字符计；仅保留首字，其后每一位用一个 `*` 代替；无昵称时返回 `*`。
 * @param {string} [name]
 */
export function maskNicknameLeadingChar(name) {
  const raw = String(name ?? '').trim()
  const chars = Array.from(raw)
  const n = chars.length
  if (n <= 2) return '*'
  return chars[0] + '*'.repeat(n - 1)
}
