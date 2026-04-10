import { onBeforeUnmount } from 'vue'
import { api } from '../utils/api'

/**
 * 微信分享奖励：在「即将弹出分享面板」时由 onShareAppMessage 触发请求（含右上角菜单转发、open-type="share" 按钮）。
 * 游戏内「分享+1次」点击会先 markShareIntent，随后同一轮也会进 onShareAppMessage，用 dedupeWindowMs 合并为一次请求。
 * 后端仍有最小间隔风控。
 *
 * @param {{ value: number }} hintAnswerQuotaRef
 * @param {{ rewardDelayMs?: number, dedupeWindowMs?: number, enabled?: boolean }} [options]
 */
export function usePunShareReward(hintAnswerQuotaRef, options = {}) {
  const enabled = options.enabled !== false
  const rewardDelayMs = options.rewardDelayMs ?? 0
  /** 同一次分享动作内可能先后触发 click + onShareAppMessage，合并为单次领奖 */
  const dedupeWindowMs = options.dedupeWindowMs ?? 900
  let rewardTimer = null
  let dedupeUntil = 0

  async function claimShareReward(add = 1) {
    try {
      const data = await api.claimHintShareReward(add)
      if (typeof data.hintAnswerQuota === 'number') {
        hintAnswerQuotaRef.value = data.hintAnswerQuota
      } else {
        hintAnswerQuotaRef.value += add
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

  /** 游戏内分享按钮 @share-intent，与随后 onShareAppMessage 去重 */
  function markShareIntent() {
    scheduleShareRewardClaim()
  }

  /** 在 onShareAppMessage 里包一层 return，会先调度领奖再返回分享参数 */
  function withShareReward(payloadFactory) {
    scheduleShareRewardClaim()
    const payload = typeof payloadFactory === 'function' ? payloadFactory() : (payloadFactory || {})
    return payload
  }

  onBeforeUnmount(() => {
    if (rewardTimer) {
      clearTimeout(rewardTimer)
      rewardTimer = null
    }
  })

  return {
    markShareIntent,
    withShareReward,
  }
}
