/** 与服务端 explain_fallback 一致 */
export const DEFAULT_PASS_EXPLAIN_FALLBACK = '请点击进入下一关吧~'

function normalizeText(v) {
  return String(v || '').replace(/\s+/g, ' ').trim()
}

/**
 * 解析 submit 返回的 passExplain；空则回退默认文案。
 * @param {string} [apiExplain]
 * @returns {string}
 */
export function resolvePassExplain(apiExplain) {
  const text = normalizeText(apiExplain)
  return text || DEFAULT_PASS_EXPLAIN_FALLBACK
}
