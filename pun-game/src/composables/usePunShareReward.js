import { onBeforeUnmount } from 'vue'
import { api } from '../utils/api'

/**
 * 微信分享奖励：仅当用户先点击分享按钮，再在 onShareAppMessage success 回调里发放奖励。
 * 规则：分享成功后延迟 rewardDelayMs 发放；取消/失败不发放。
 *
 * @param {{ value: number }} hintAnswerQuotaRef
 * @param {{ rewardDelayMs?: number, intentKeepAliveMs?: number }} [options]
 */
export function usePunShareReward(hintAnswerQuotaRef, options = {}) {
  const rewardDelayMs = options.rewardDelayMs ?? 2000
  const intentKeepAliveMs = options.intentKeepAliveMs ?? 30000
  let shareIntent = false
  let intentTimer = null
  let rewardTimer = null

  function clearIntent() {
    shareIntent = false
    if (intentTimer) {
      clearTimeout(intentTimer)
      intentTimer = null
    }
  }

  function markShareIntent() {
    shareIntent = true
    if (intentTimer) clearTimeout(intentTimer)
    intentTimer = setTimeout(() => {
      clearIntent()
    }, intentKeepAliveMs)
  }

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

  function withShareReward(payloadFactory) {
    const payload = typeof payloadFactory === 'function' ? payloadFactory() : (payloadFactory || {})
    return {
      ...payload,
      success: () => {
        if (!shareIntent) return
        clearIntent()
        if (rewardTimer) clearTimeout(rewardTimer)
        rewardTimer = setTimeout(() => {
          rewardTimer = null
          claimShareReward(1)
        }, rewardDelayMs)
      },
      fail: () => {
        clearIntent()
      },
    }
  }

  onBeforeUnmount(() => {
    clearIntent()
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
