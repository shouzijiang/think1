import { REWARDED_VIDEO_AD_UNIT_ID } from '../constants/rewardedVideoAd'

/** 广告 load 超时（与任务页原逻辑一致） */
export const REWARDED_VIDEO_LOAD_TIMEOUT_MS = 10000

function getRewardedVideoCreator() {
  if (typeof wx !== 'undefined' && typeof wx.createRewardedVideoAd === 'function') {
    return wx.createRewardedVideoAd.bind(wx)
  }
  if (typeof tt !== 'undefined' && typeof tt.createRewardedVideoAd === 'function') {
    return tt.createRewardedVideoAd.bind(tt)
  }
  return null
}

export function isRewardedVideoSupported() {
  return !!getRewardedVideoCreator()
}

/**
 * 创建激励视频实例；onError 时回调 onInvalid（用于置空单例并释放 busy）
 * @param {{ onInvalid?: () => void, adUnitId?: string }} [opts]
 */
export function createManagedRewardedVideoAd(opts = {}) {
  const creator = getRewardedVideoCreator()
  if (!creator) return null

  try {
    const adUnitId = opts.adUnitId || REWARDED_VIDEO_AD_UNIT_ID
    const ad = creator({ adUnitId })
    if (!ad || typeof ad.show !== 'function') {
      return null
    }

    if (typeof ad.onLoad === 'function') {
      ad.onLoad(() => {})
    }
    if (typeof ad.onError === 'function') {
      ad.onError((err) => {
        console.warn('[rewardedVideo] onError', err)
        opts.onInvalid?.()
      })
    }

    return ad
  } catch (e) {
    console.warn('[rewardedVideo] create failed', e)
    return null
  }
}

export function safeOffRewardedVideoClose(ad, handler) {
  if (ad && handler && typeof ad.offClose === 'function') {
    try {
      ad.offClose(handler)
    } catch (_) {}
  }
}

function loadAdWithTimeout(ad, ms) {
  return new Promise((resolve, reject) => {
    if (!ad || typeof ad.load !== 'function') {
      reject(new Error('广告实例无效'))
      return
    }
    const timer = setTimeout(() => reject(new Error('广告加载超时')), ms)
    ad.load()
      .then(() => {
        clearTimeout(timer)
        resolve()
      })
      .catch((e) => {
        clearTimeout(timer)
        reject(e)
      })
  })
}

/**
 * 安全展示：先 show，失败则 load 后重试 show
 * @param {object} ad
 * @param {{ loadTimeoutMs?: number }} [opts]
 */
export function showRewardedVideoAd(ad, opts = {}) {
  const loadTimeoutMs = opts.loadTimeoutMs ?? REWARDED_VIDEO_LOAD_TIMEOUT_MS

  const tryShow = () => {
    if (!ad || typeof ad.show !== 'function') {
      return Promise.reject(new Error('广告实例无效'))
    }
    return ad.show()
  }

  return tryShow().catch(() =>
    loadAdWithTimeout(ad, loadTimeoutMs).then(() => tryShow())
  )
}

export function destroyRewardedVideoAd(ad) {
  if (ad && typeof ad.destroy === 'function') {
    try {
      ad.destroy()
    } catch (_) {}
  }
}

export function rewardedVideoFailToast(err) {
  const msg =
    err && typeof err === 'object' && err.message === '广告加载超时'
      ? '广告加载超时，请稍后重试'
      : '广告加载失败，请稍后重试'
  uni.showToast({ title: msg, icon: 'none' })
}

/**
 * 安全读取启动 scene（避免部分基础库访问 referrerInfo 抛错）
 */
export function safeGetLaunchScene() {
  try {
    if (typeof uni.getEnterOptionsSync === 'function') {
      const enter = uni.getEnterOptionsSync()
      if (enter && enter.scene != null && enter.scene !== '') {
        return enter.scene
      }
    }
  } catch (_) {}
  try {
    if (typeof uni.getLaunchOptionsSync === 'function') {
      const launch = uni.getLaunchOptionsSync()
      if (launch && launch.scene != null && launch.scene !== '') {
        return launch.scene
      }
    }
  } catch (_) {}
  return undefined
}
