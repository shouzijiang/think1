import { onBeforeUnmount } from 'vue'
import { onShow, onHide } from '@dcloudio/uni-app'
import { api } from '../utils/api'

/**
 * 微信分享奖励：
 * - 默认模式：在「即将弹出分享面板」时由 onShareAppMessage 触发请求（含右上角菜单转发、open-type="share" 按钮）。
 * - heuristic 模式：记录分享意图；当页面从后台回前台且后台停留时长 >= 阈值时才发奖，短停留判定为“分享取消”。
 *   （用于微信没有真实分享回调时的近似判定）
 *
 * @param {{ value: number }} hintAnswerQuotaRef
 * @param {{
 *   rewardDelayMs?: number,
 *   dedupeWindowMs?: number,
 *   enabled?: boolean,
 *   mode?: 'instant'|'heuristic',
 *   shareSuccessThresholdMs?: number,
 *   showCancelToast?: boolean
 * }} [options]
 */
export function usePunShareReward(hintAnswerQuotaRef, options = {}) {
  const enabled = options.enabled !== false
  const rewardDelayMs = options.rewardDelayMs ?? 0
  const onClaimSuccess = typeof options.onClaimSuccess === 'function' ? options.onClaimSuccess : null
  const mode = options.mode === 'heuristic' ? 'heuristic' : 'instant'
  const shareSuccessThresholdMs = Number(options.shareSuccessThresholdMs) > 0
    ? Number(options.shareSuccessThresholdMs)
    : 3000
  const showCancelToast = options.showCancelToast === true
  /** 同一次分享动作内可能先后触发 click + onShareAppMessage，合并为单次领奖 */
  const dedupeWindowMs = options.dedupeWindowMs ?? 900
  let rewardTimer = null
  let dedupeUntil = 0
  let sharePending = false
  let shareHideAt = 0
  let noHideFallbackTimer = null

  async function claimShareReward(add = 1) {
    try {
      const data = await api.claimReward({ type: 'share', add })
      if (typeof data.hintAnswerQuota === 'number') {
        hintAnswerQuotaRef.value = data.hintAnswerQuota
      } else {
        hintAnswerQuotaRef.value += add
      }
      if (onClaimSuccess) {
        onClaimSuccess({
          added: Number(data && data.added) > 0 ? Number(data.added) : add,
          quota: hintAnswerQuotaRef.value,
          type: data && data.type ? String(data.type) : 'share',
        })
      }
      uni.showToast({ title: `分享成功，次数+${add}`, icon: 'none' })
    } catch (e) {
      uni.showToast({ title: e.message || '奖励发放失败', icon: 'none' })
    }
  }

  function scheduleShareRewardClaim() {
    if (!enabled) return
    const now = Date.now()
    if (now < dedupeUntil) return
    dedupeUntil = now + dedupeWindowMs

    if (rewardTimer) {
      clearTimeout(rewardTimer)
      rewardTimer = null
    }
    rewardTimer = setTimeout(() => {
      rewardTimer = null
      claimShareReward(1)
    }, rewardDelayMs)
  }

  function beginShareIntent() {
    sharePending = true
    shareHideAt = 0
    // 微信开发者工具里有时不会触发 onHide/onShow，这里给一个兜底“取消”提示
    if (noHideFallbackTimer) {
      clearTimeout(noHideFallbackTimer)
      noHideFallbackTimer = null
    }
    if (mode === 'heuristic') {
      noHideFallbackTimer = setTimeout(() => {
        noHideFallbackTimer = null
        if (!sharePending || shareHideAt > 0) return
        sharePending = false
        if (showCancelToast) {
          uni.showToast({ title: '分享取消', icon: 'none' })
        }
      }, 1200)
    }
  }

  function finalizeShareHeuristicIfNeeded() {
    if (!enabled || mode !== 'heuristic' || !sharePending || shareHideAt <= 0) return
    const stayTime = Date.now() - shareHideAt
    sharePending = false
    shareHideAt = 0
    if (stayTime >= shareSuccessThresholdMs) {
      scheduleShareRewardClaim()
    } else if (showCancelToast) {
      uni.showToast({ title: '分享取消', icon: 'none' })
    }
  }

  /** 游戏内分享按钮 @share-intent，与随后 onShareAppMessage 去重 */
  function markShareIntent() {
    if (mode === 'heuristic') {
      beginShareIntent()
      return
    }
    scheduleShareRewardClaim()
  }

  /** 在 onShareAppMessage 里包一层 return，会先调度领奖再返回分享参数 */
  function withShareReward(payloadFactory) {
    if (mode === 'heuristic') {
      beginShareIntent()
    } else {
      scheduleShareRewardClaim()
    }
    const payload = typeof payloadFactory === 'function' ? payloadFactory() : (payloadFactory || {})
    return payload
  }

  if (mode === 'heuristic') {
    onHide(() => {
      if (!sharePending) return
      if (noHideFallbackTimer) {
        clearTimeout(noHideFallbackTimer)
        noHideFallbackTimer = null
      }
      shareHideAt = Date.now()
    })
    onShow(() => {
      finalizeShareHeuristicIfNeeded()
    })
  }

  onBeforeUnmount(() => {
    if (rewardTimer) {
      clearTimeout(rewardTimer)
      rewardTimer = null
    }
    if (noHideFallbackTimer) {
      clearTimeout(noHideFallbackTimer)
      noHideFallbackTimer = null
    }
    sharePending = false
    shareHideAt = 0
  })

  return {
    markShareIntent,
    withShareReward,
  }
}
