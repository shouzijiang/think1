import { onBeforeUnmount } from 'vue'
import { api } from '../utils/api'
import { REWARDED_VIDEO_AD_UNIT_ID } from '../constants/rewardedVideoAd'

/**
 * 揭字次数为 0 时：拉起微信激励视频，完整观看后调用后端发放 +1 次答案次数。
 * 仅微信小程序端有效；其它端请走分享等既有逻辑。
 *
 * @param {{ value: number }} hintAnswerQuotaRef
 */
export function usePunRewardedVideoHint(hintAnswerQuotaRef) {
  let videoAd = null
  let adBusy = false

  // #ifdef MP-WEIXIN
  function ensureVideoAd() {
    if (videoAd) {
      return videoAd
    }
    if (typeof wx === 'undefined' || typeof wx.createRewardedVideoAd !== 'function') {
      return null
    }
    const ad = wx.createRewardedVideoAd({ adUnitId: REWARDED_VIDEO_AD_UNIT_ID })
    ad.onLoad(() => {})
    ad.onError((err) => {
      console.error('激励视频广告', err)
    })
    videoAd = ad
    return videoAd
  }
  // #endif

  /**
   * 展示激励视频；完整观看并成功领奖后返回 true。
   */
  async function tryWatchAdForHintQuota() {
    // #ifndef MP-WEIXIN
    return false
    // #endif

    // #ifdef MP-WEIXIN
    if (adBusy) {
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
        try {
          ad.offClose(onClose)
        } catch (_) {}

        if (res && res.isEnded) {
          api
            .claimHintRewardVideoAd(1)
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

      ad.onClose(onClose)

      const showAd = () => {
        ad.show()
          .then(() => {})
          .catch(() => {
            ad.load()
              .then(() => ad.show())
              .catch((err) => {
                console.error('激励视频显示失败', err)
                try {
                  ad.offClose(onClose)
                } catch (_) {}
                uni.showToast({ title: '广告加载失败，请稍后重试', icon: 'none' })
                finish(false)
              })
          })
      }

      showAd()
    })
    // #endif
  }

  onBeforeUnmount(() => {
    // #ifdef MP-WEIXIN
    if (videoAd && typeof videoAd.destroy === 'function') {
      try {
        videoAd.destroy()
      } catch (_) {}
      videoAd = null
    }
    adBusy = false
    // #endif
  })

  return {
    tryWatchAdForHintQuota,
  }
}
