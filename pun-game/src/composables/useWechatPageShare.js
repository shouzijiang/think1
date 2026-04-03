/**
 * 微信小程序：未单独写分享的页面，使用当前路径 + query 作为转发/朋友圈落地页。
 */
// #ifdef MP-WEIXIN
import { onShareAppMessage, onShareTimeline } from '@dcloudio/uni-app'

function currentSharePath() {
  const stack = getCurrentPages()
  const page = stack[stack.length - 1]
  if (!page) return '/pages/index/index'
  const route = page.route || ''
  const path = route.startsWith('/') ? route : `/${route}`
  const opt = page.options || {}
  const parts = Object.keys(opt)
    .map((k) => {
      const v = opt[k]
      if (v === undefined || v === '') return ''
      return `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`
    })
    .filter(Boolean)
  return parts.length ? `${path}?${parts.join('&')}` : path
}

function currentTimelineQuery() {
  const full = currentSharePath()
  const q = full.indexOf('?')
  return q >= 0 ? full.slice(q + 1) : ''
}

/**
 * @param {string | (() => string)} titleOrFn
 */
export function useWechatPageShare(titleOrFn) {
  const getTitle = typeof titleOrFn === 'function' ? titleOrFn : () => titleOrFn || '谐音梗图'

  onShareAppMessage(() => ({
    title: getTitle(),
    path: currentSharePath(),
  }))

  onShareTimeline(() => ({
    title: getTitle(),
    query: currentTimelineQuery(),
  }))
}
// #endif
// #ifndef MP-WEIXIN
export function useWechatPageShare() {}
// #endif
