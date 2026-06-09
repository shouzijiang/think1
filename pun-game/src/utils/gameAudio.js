/**
 * 游戏 BGM / 音效
 *
 * - 全端（含微信小程序）：BGM 与音效均使用 uni.createInnerAudioContext
 * - 说明：不再使用 wx.getBackgroundAudioManager，可避免微信系统音乐控件常驻显示
 */

const BGM_STORAGE_KEY = 'pun_game_bgm_enabled'
const SFX_STORAGE_KEY = 'pun_game_sfx_enabled'

const BGM_HOME = 'https://static2.sofun.online/mp3/BGMMain.mp3'
const BGM_PLAY = 'https://static2.sofun.online/mp3/BGMGame1.mp3'
const SFX_CONGRATS = 'https://static2.sofun.online/mp3/SFXCongrats.mp3'
const SFX_ERROR = 'https://static2.sofun.online/mp3/error.mp3'
let bgmCtx = null
let sfxCongratsCtx = null
let sfxErrorCtx = null
let currentBgmUrl = ''
/** 远程 URL -> downloadFile 的 tempFilePath（播放用本地路径，避免重复 GET） */
/** @type {Map<string, string>} */
const urlToLocalPath = new Map()
/** @type {Map<string, Promise<string>>} */
const downloadPromises = new Map()
const localAudioSwitchCache = Object.create(null)
const PLAY_BGM_ROUTE_SET = new Set([
  'pages-sub/play/play',
  'pages-sub/playMid/playMid',
  'pages-sub/playXhs/playXhs',
  'pages-sub/battlePlay/battlePlay',
])

function getGlobalDataRef() {
  try {
    const app = typeof getApp === 'function' ? getApp() : null
    if (!app) return null
    if (!app.globalData) app.globalData = {}
    return app.globalData
  } catch {
    return null
  }
}

function getGlobalAudioSwitchCache() {
  const globalData = getGlobalDataRef()
  if (!globalData) return null
  if (!globalData.__punAudioSwitchCache) {
    globalData.__punAudioSwitchCache = Object.create(null)
  }
  return globalData.__punAudioSwitchCache
}

function canDownloadAudio() {
  return typeof uni !== 'undefined' && typeof uni.downloadFile === 'function'
}

/**
 * 下载音频并返回可播放 src（优先 tempFilePath，失败则回退远程 URL）。
 * @param {string} url
 * @returns {Promise<string>}
 */
function ensureAudioSrc(url) {
  if (!url) return Promise.resolve('')
  const local = urlToLocalPath.get(url)
  if (local) return Promise.resolve(local)
  if (downloadPromises.has(url)) {
    return downloadPromises.get(url)
  }
  if (!canDownloadAudio()) {
    return Promise.resolve(url)
  }

  const p = new Promise((resolve) => {
    uni.downloadFile({
      url,
      success(res) {
        if (res.statusCode === 200 && res.tempFilePath) {
          urlToLocalPath.set(url, res.tempFilePath)
          resolve(res.tempFilePath)
          return
        }
        resolve(url)
      },
      fail() {
        resolve(url)
      },
    })
  }).finally(() => {
    downloadPromises.delete(url)
  })

  downloadPromises.set(url, p)
  return p
}

/**
 * 按 URL 预热音频（已下载过的 URL 会跳过）。
 * @param {string[]} urls
 */
export function preloadAudioUrls(urls) {
  if (!Array.isArray(urls) || urls.length === 0) {
    return Promise.resolve()
  }
  const pending = urls.filter((url) => url && !urlToLocalPath.has(url))
  if (pending.length === 0) {
    return Promise.resolve()
  }
  return Promise.all(pending.map((url) => ensureAudioSrc(url))).then(() => {})
}

/**
 * 兼容旧调用：默认仅预加载首页 BGM，避免启动时并行下载全部 mp3。
 * @param {string[]} [urls]
 */
export function preloadGameAudio(urls = [BGM_HOME]) {
  return preloadAudioUrls(urls)
}

/** 闯关页进入前可预加载游戏 BGM 与常用音效 */
export function preloadGameAudioForPlay() {
  return preloadAudioUrls([BGM_PLAY, SFX_CONGRATS, SFX_ERROR])
}

function applyCtxDefaults(ctx, loop) {
  ctx.loop = !!loop
  try {
    if (typeof ctx.volume === 'number') {
      ctx.volume = 1
    }
  } catch (e) {
    /* noop */
  }
  // #ifdef MP
  try {
    if ('obeyMuteSwitch' in ctx) {
      ctx.obeyMuteSwitch = false
    }
  } catch (e) {
    /* noop */
  }
  // #endif
}

function readAudioSwitch(storageKey) {
  if (Object.prototype.hasOwnProperty.call(localAudioSwitchCache, storageKey)) {
    return localAudioSwitchCache[storageKey]
  }
  const globalCache = getGlobalAudioSwitchCache()
  if (globalCache && Object.prototype.hasOwnProperty.call(globalCache, storageKey)) {
    const val = !!globalCache[storageKey]
    localAudioSwitchCache[storageKey] = val
    return val
  }

  localAudioSwitchCache[storageKey] = true
  if (globalCache) {
    globalCache[storageKey] = true
  }
  return true
}

function writeAudioSwitch(storageKey, on) {
  const val = !!on
  localAudioSwitchCache[storageKey] = val
  const globalCache = getGlobalAudioSwitchCache()
  if (globalCache) {
    globalCache[storageKey] = val
  }
  uni.setStorage({ key: storageKey, data: val, fail() {} })
}

export function isBgmEnabled() {
  return readAudioSwitch(BGM_STORAGE_KEY)
}

export function isSfxEnabled() {
  return readAudioSwitch(SFX_STORAGE_KEY)
}

/**
 * 兼容旧接口：等同于 isBgmEnabled
 */
export function isGameAudioEnabled() {
  return isBgmEnabled()
}

/**
 * @param {boolean} on
 */
export function setBgmEnabled(on) {
  writeAudioSwitch(BGM_STORAGE_KEY, on)
  if (!on) {
    stopBgm()
  }
}

/**
 * @param {boolean} on
 */
export function setSfxEnabled(on) {
  writeAudioSwitch(SFX_STORAGE_KEY, on)
  if (!on) {
    stopCongratsSfx()
    stopErrorSfx()
  }
}

/**
 * 兼容旧接口：等同于 setBgmEnabled
 * @param {boolean} on
 */
export function setGameAudioEnabled(on) {
  setBgmEnabled(on)
}

function getBgmContextInner() {
  if (!bgmCtx) {
    bgmCtx = uni.createInnerAudioContext()
    applyCtxDefaults(bgmCtx, true)
  }
  return bgmCtx
}

function getCongratsContext() {
  if (!sfxCongratsCtx) {
    sfxCongratsCtx = uni.createInnerAudioContext()
    applyCtxDefaults(sfxCongratsCtx, false)
  }
  return sfxCongratsCtx
}

function getErrorContext() {
  if (!sfxErrorCtx) {
    sfxErrorCtx = uni.createInnerAudioContext()
    applyCtxDefaults(sfxErrorCtx, false)
  }
  return sfxErrorCtx
}

function safePlay(ctx) {
  try {
    ctx.play()
  } catch (e) {
    console.warn('[gameAudio] play error', e)
  }
}

function playWhenReady(ctx, playFn) {
  const kick = () => playFn()
  kick()
  setTimeout(kick, 120)
  setTimeout(kick, 360)
  setTimeout(kick, 800)
  try {
    if (typeof ctx.onCanplay === 'function') {
      const onReady = () => {
        try {
          if (typeof ctx.offCanplay === 'function') {
            ctx.offCanplay(onReady)
          }
        } catch (e) {
          /* noop */
        }
        kick()
      }
      try {
        if (typeof ctx.offCanplay === 'function') {
          ctx.offCanplay(onReady)
        }
      } catch (e) {
        /* noop */
      }
      ctx.onCanplay(onReady)
    }
  } catch (e) {
    /* noop */
  }
}

/**
 * @param {string} remoteUrl 逻辑曲目 id（远程 URL）
 * @param {string} playSrc 实际传给 InnerAudioContext 的 src
 */
function playBgmInner(remoteUrl, playSrc) {
  if (!isBgmEnabled()) return
  const ctx = getBgmContextInner()
  applyCtxDefaults(ctx, true)
  const src = playSrc || urlToLocalPath.get(remoteUrl) || remoteUrl

  if (currentBgmUrl === remoteUrl) {
    // 同一首歌：仅在暂停状态下恢复，避免切页时重复 play 导致重头播放
    if (ctx.paused) {
      playWhenReady(ctx, () => safePlay(ctx))
    }
    return
  }

  currentBgmUrl = remoteUrl
  try {
    ctx.stop()
  } catch (e) {
    /* noop */
  }
  ctx.src = src
  playWhenReady(ctx, () => safePlay(ctx))
}

function playBgm(url) {
  if (!isBgmEnabled()) return
  // 优先使用已缓存的本地路径，否则直接用远程 URL 流式播放，无需等待完整下载
  const src = urlToLocalPath.get(url) || url
  playBgmInner(url, src)
  // 后台静默缓存，供下次复用（不阻塞当前播放）
  if (!urlToLocalPath.has(url)) {
    ensureAudioSrc(url)
  }
}

export function playBgmHome() {
  playBgm(BGM_HOME)
}

export function playBgmPlay() {
  playBgm(BGM_PLAY)
}

function normalizeRoute(route) {
  if (!route) return ''
  let next = String(route).trim()
  if (!next) return ''
  if (next.startsWith('/')) next = next.slice(1)
  if (next.endsWith('.html')) next = next.slice(0, -5)
  return next
}

export function syncBgmByRoute(route) {
  const normalizedRoute = normalizeRoute(route)
  if (!normalizedRoute) {
    playBgmHome()
    return
  }
  if (PLAY_BGM_ROUTE_SET.has(normalizedRoute)) {
    playBgmPlay()
    return
  }
  playBgmHome()
}

export function syncBgmByCurrentPage() {
  try {
    const pages = typeof getCurrentPages === 'function' ? getCurrentPages() : []
    const current = Array.isArray(pages) && pages.length > 0 ? pages[pages.length - 1] : null
    const route = current?.route || ''
    syncBgmByRoute(route)
  } catch (e) {
    playBgmHome()
  }
}

export function stopBgm() {
  currentBgmUrl = ''
  if (!bgmCtx) return
  try {
    bgmCtx.stop()
  } catch (e) {
    /* noop */
  }
}

function playSfxOnce(remoteUrl, getCtx) {
  // 优先使用已缓存的本地路径，否则直接用远程 URL 流式播放，无需等待完整下载
  const src = urlToLocalPath.get(remoteUrl) || remoteUrl
  const ctx = getCtx()
  applyCtxDefaults(ctx, false)
  try {
    ctx.stop()
  } catch (e) {
    /* noop */
  }
  ctx.src = src
  playWhenReady(ctx, () => safePlay(ctx))
  // 后台静默缓存，供下次复用（不阻塞当前播放）
  if (!urlToLocalPath.has(remoteUrl)) {
    ensureAudioSrc(remoteUrl)
  }
}

export function playCongratsOnce() {
  if (!isSfxEnabled()) return
  playSfxOnce(SFX_CONGRATS, getCongratsContext)
}

export function playErrorOnce() {
  if (!isSfxEnabled()) return
  playSfxOnce(SFX_ERROR, getErrorContext)
}

function stopCongratsSfx() {
  if (!sfxCongratsCtx) return
  try {
    sfxCongratsCtx.stop()
  } catch (e) {
    /* noop */
  }
}

function stopErrorSfx() {
  if (!sfxErrorCtx) return
  try {
    sfxErrorCtx.stop()
  } catch (e) {
    /* noop */
  }
}
