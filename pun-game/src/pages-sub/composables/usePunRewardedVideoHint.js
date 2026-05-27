import { onBeforeUnmount } from 'vue'
import { api } from '../../utils/api'
import {
  createManagedRewardedVideoAd,
  destroyRewardedVideoAd,
  isRewardedVideoSupported,
  rewardedVideoFailToast,
  safeOffRewardedVideoClose,
  showRewardedVideoAd,
} from '../utils/rewardedVideoRunner'

/**
 * 揭字次数为 0 时：拉起激励视频（微信/抖音小程序），完整观看后调用后端发放 +1 次答案次数。
 * 非小程序端返回 false，请走分享等既有逻辑。
 *
 * @param {{ value: number }} hintAnswerQuotaRef
 */
export function usePunRewardedVideoHint(hintAnswerQuotaRef) {
  let videoAd = null
  let adBusy = false

  function invalidateAd() {
    videoAd = null
    adBusy = false
  }

  function ensureVideoAd() {
    if (videoAd && typeof videoAd.show === 'function') {
      return videoAd
    }
    videoAd = null
    videoAd = createManagedRewardedVideoAd({ onInvalid: invalidateAd })
    return videoAd
  }

  /**
   * 展示激励视频；完整观看并成功领奖后返回 true。
   */
  async function tryWatchAdForHintQuota() {
    if (adBusy) {
      return false
    }
    if (!isRewardedVideoSupported()) {
      uni.showToast({ title: '当前环境不支持激励视频', icon: 'none' })
      return false
    }

    const ad = ensureVideoAd()
    if (!ad) {
      uni.showToast({ title: '当前环境不支持激励视频', icon: 'none' })
      return false
    }

    adBusy = true

    return new Promise((resolve) => {
      let settled = false
      const finish = (ok) => {
        if (settled) return
        settled = true
        adBusy = false
        resolve(ok)
      }

      const onClose = (res) => {
        safeOffRewardedVideoClose(ad, onClose)

        if (res && res.isEnded) {
          api
            .claimReward({ type: 'reward_video', add: 1 })
            .then((data) => {
              if (typeof data.hintAnswerQuota === 'number') {
                hintAnswerQuotaRef.value = data.hintAnswerQuota
              } else {
                hintAnswerQuotaRef.value += 1
              }
              finish(true)
            })
            .catch((e) => {
              uni.showToast({ title: e.message || '领取失败', icon: 'none' })
              finish(false)
            })
        } else {
          uni.showToast({ title: '请完整观看广告可获得 1 次答案', icon: 'none' })
          finish(false)
        }
      }

      if (typeof ad.onClose !== 'function') {
        rewardedVideoFailToast(new Error('广告实例无效'))
        finish(false)
        return
      }

      ad.onClose(onClose)

      showRewardedVideoAd(ad)
        .catch((err) => {
          console.warn('[rewardedVideo] show failed', err)
          safeOffRewardedVideoClose(ad, onClose)
          invalidateAd()
          rewardedVideoFailToast(err)
          finish(false)
        })
    })
  }

  onBeforeUnmount(() => {
    destroyRewardedVideoAd(videoAd)
    videoAd = null
    adBusy = false
  })

  return {
    tryWatchAdForHintQuota,
  }
}
