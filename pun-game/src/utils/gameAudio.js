/**
 * 游戏 BGM / 音效
 *
 * - 全端（含微信小程序）：BGM 与音效均使用 uni.createInnerAudioContext
 * - 说明：不再使用 wx.getBackgroundAudioManager，可避免微信系统音乐控件常驻显示
 */

const STORAGE_KEY = 'pun_game_bgm_enabled'

const BGM_HOME = 'https://static2.sofun.online/mp3/BGMMain.wav'
const BGM_PLAY = 'https://static2.sofun.online/mp3/BGMGame1.wav'
const SFX_CONGRATS = 'https://static2.sofun.online/mp3/SFXCongrats.wav'
const SFX_ERROR = 'https://static2.sofun.online/mp3/error.mp3'
const AUDIO_PRELOAD_LIST = [BGM_HOME, BGM_PLAY, SFX_CONGRATS, SFX_ERROR]

let bgmCtx = null
let sfxCongratsCtx = null
let sfxErrorCtx = null
let currentBgmUrl = ''
let preloadStarted = false
let preloadPromise = null

/**
 * 预热音频资源缓存，降低首次播放卡顿。
 * 说明：downloadFile 在小程序/uni 环境可触发本地缓存命中；失败时静默降级。
 */
export function preloadGameAudio() {
  if (preloadStarted) {
    return preloadPromise || Promise.resolve()
  }
  preloadStarted = true
  if (typeof uni === 'undefined' || typeof uni.downloadFile !== 'function') {
    preloadPromise = Promise.resolve()
    return preloadPromise
  }

  preloadPromise = Promise.all(
    AUDIO_PRELOAD_LIST.map(
      (url) =>
        new Promise((resolve) => {
          uni.downloadFile({
            url,
            complete: () => resolve(),
          })
        })
    )
  )
    .then(() => {})
    .catch(() => {})

  return preloadPromise
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

export function isGameAudioEnabled() {
  try {
    const v = uni.getStorageSync(STORAGE_KEY)
    if (v === '' || v === undefined || v === null) return true
    return v === true || v === 'true' || v === 1 || v === '1'
  } catch {
    return true
  }
}

/**
 * @param {boolean} on
 */
export function setGameAudioEnabled(on) {
  uni.setStorageSync(STORAGE_KEY, !!on)
  if (!on) {
    stopBgm()
    stopCongratsSfx()
    stopErrorSfx()
  }
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

function playBgmInner(url) {
  if (!isGameAudioEnabled()) return
  const ctx = getBgmContextInner()
  applyCtxDefaults(ctx, true)

  if (currentBgmUrl === url) {
    playWhenReady(ctx, () => safePlay(ctx))
    return
  }

  currentBgmUrl = url
  try {
    ctx.stop()
  } catch (e) {
    /* noop */
  }
  ctx.src = url
  playWhenReady(ctx, () => safePlay(ctx))
}

function playBgm(url) {
  if (!isGameAudioEnabled()) return
  preloadGameAudio()
  playBgmInner(url)
}

export function playBgmHome() {
  playBgm(BGM_HOME)
}

export function playBgmPlay() {
  playBgm(BGM_PLAY)
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

export function playCongratsOnce() {
  if (!isGameAudioEnabled()) return
  preloadGameAudio()
  const ctx = getCongratsContext()
  applyCtxDefaults(ctx, false)
  try {
    ctx.stop()
  } catch (e) {
    /* noop */
  }
  ctx.src = SFX_CONGRATS
  playWhenReady(ctx, () => safePlay(ctx))
}

export function playErrorOnce() {
  if (!isGameAudioEnabled()) return
  preloadGameAudio()
  const ctx = getErrorContext()
  applyCtxDefaults(ctx, false)
  try {
    ctx.stop()
  } catch (e) {
    /* noop */
  }
  ctx.src = SFX_ERROR
  playWhenReady(ctx, () => safePlay(ctx))
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
