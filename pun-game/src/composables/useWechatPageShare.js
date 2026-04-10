/**
 * 微信小程序：未单独写分享的页面，使用当前路径 + query 作为转发/朋友圈落地页。
 * 传入 hintAnswerQuotaRef 时，右上角「转发」也会走分享领奖（与 usePunShareReward 一致）。
 */
// #ifdef MP-WEIXIN
import { ref } from 'vue'
import { onShareAppMessage, onShareTimeline } from '@dcloudio/uni-app'
import { usePunShareReward } from './usePunShareReward'

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

/** 与 App.onShow 互补：进入使用本 composable 的页面时再开一次菜单（自定义导航页易丢转发入口） */
function ensureMpShareMenu() {
  if (typeof uni.showShareMenu !== 'function') return
  uni.showShareMenu({
    withShareTicket: true,
    menus: ['shareAppMessage', 'shareTimeline'],
    fail(err) {
      console.warn('[showShareMenu]', err)
    },
  })
}

/**
 * @param {string | (() => string)} titleOrFn
 * @param {import('vue').Ref<number> | null} [hintAnswerQuotaRef] 传入则转发时尝试领取分享奖励并回写剩余次数
 */
export function useWechatPageShare(titleOrFn, hintAnswerQuotaRef = null) {
  ensureMpShareMenu()

  const getTitle = typeof titleOrFn === 'function' ? titleOrFn : () => titleOrFn || '谐音梗图'

  const noopQuotaRef = ref(0)
  const quotaRef = hintAnswerQuotaRef ?? noopQuotaRef
  const { withShareReward } = usePunShareReward(quotaRef, {
    enabled: hintAnswerQuotaRef != null,
  })

  onShareAppMessage(() => withShareReward({
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
