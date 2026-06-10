import { INTERSTITIAL_AD_UNIT_ID } from '../constants/interstitialAd'

function getInterstitialAdCreator() {
  if (typeof wx !== 'undefined' && typeof wx.createInterstitialAd === 'function') {
    return wx.createInterstitialAd.bind(wx)
  }
  if (typeof tt !== 'undefined' && typeof tt.createInterstitialAd === 'function') {
    return tt.createInterstitialAd.bind(tt)
  }
  return null
}

export function isInterstitialAdSupported() {
  return !!getInterstitialAdCreator()
}

function createInterstitialAd() {
  const creator = getInterstitialAdCreator()
  if (!creator) return null
  try {
    const ad = creator({ adUnitId: INTERSTITIAL_AD_UNIT_ID })
    if (!ad || typeof ad.show !== 'function') return null
    return ad
  } catch (e) {
    console.warn('[interstitialAd] create failed', e)
    return null
  }
}

export function destroyInterstitialAd(ad) {
  if (ad && typeof ad.destroy === 'function') {
    try {
      ad.destroy()
    } catch (_) {}
  }
}

/**
 * 创建并展示插屏广告。
 * 对应官方示例：create → onLoad/onError/onClose → show。
 * 广告关闭或出错后自动销毁实例。
 */
export function showInterstitialAd() {
  if (!isInterstitialAdSupported()) return

  const ad = createInterstitialAd()
  if (!ad) return

  let settled = false
  const cleanup = () => {
    if (settled) return
    settled = true
    destroyInterstitialAd(ad)
  }

  if (typeof ad.onLoad === 'function') {
    ad.onLoad(() => {})
  }
  if (typeof ad.onError === 'function') {
    ad.onError((err) => {
      console.warn('[interstitialAd] onError', err)
      cleanup()
    })
  }
  if (typeof ad.onClose === 'function') {
    ad.onClose(() => {
      cleanup()
    })
  }

  ad.show().catch((err) => {
    console.warn('[interstitialAd] show failed', err)
    cleanup()
  })
}
