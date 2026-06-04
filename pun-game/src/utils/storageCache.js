/**
 * 启动阶段 storage：异步预热 + 内存读取，避免首屏前 getStorageSync / setStorageSync。
 */

const CHANNEL_KEY = 'pun_channel'
const TOKEN_KEY = 'token'
const USER_INFO_KEY = 'userInfo'
const BGM_KEY = 'pun_game_bgm_enabled'
const SFX_KEY = 'pun_game_sfx_enabled'
const TOUCH_DATE_KEY = 'pun_last_login_touch_date'

/** @type {string | null | undefined} undefined=未读 storage，null=无值 */
let pendingChannelMem

/** @type {Map<string, unknown>} */
const memStore = new Map()
const changelogCache = new Map()

let warmPromise = null
let warmDone = false

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

function getStorageAsync(key) {
  return new Promise((resolve) => {
    uni.getStorage({
      key,
      success: (res) => resolve(res.data),
      fail: () => resolve(undefined),
    })
  })
}

function parseBoolStorage(v) {
  if (v === '' || v === undefined || v === null) return true
  return v === true || v === 'true' || v === 1 || v === '1'
}

function parseJsonStorage(raw) {
  if (!raw) return null
  try {
    return typeof raw === 'string' ? JSON.parse(raw) : raw
  } catch {
    return null
  }
}

function applyWarmToGlobalCaches(data) {
  const globalData = getGlobalDataRef()
  const token = data[TOKEN_KEY] ? String(data[TOKEN_KEY]) : ''
  const userInfo = data[USER_INFO_KEY] ?? null

  if (globalData) {
    globalData.__punAuthToken = token
    globalData.__punAuthUserInfo = userInfo
    globalData.__punAuthCacheLoaded = true
    globalData.__punTokenCache = token
    globalData.__punTokenCacheLoaded = true
    globalData.__punStartupStorageReady = true
    globalData.__punAudioSwitchCache = {
      [BGM_KEY]: parseBoolStorage(data[BGM_KEY]),
      [SFX_KEY]: parseBoolStorage(data[SFX_KEY]),
    }
    globalData.__punLastTouchDate = data[TOUCH_DATE_KEY] ? String(data[TOUCH_DATE_KEY]) : ''
    globalData.__punLastTouchDateLoaded = true
  }

  const channel = data[CHANNEL_KEY]
  pendingChannelMem = channel ? String(channel) : null

  changelogCache.set('pun_changelog_suppress', parseJsonStorage(data.pun_changelog_suppress))
}

/**
 * App.onLaunch 后异步执行（不阻塞首屏）。首页 onShow 可 await 同一 Promise。
 * @returns {Promise<void>}
 */
export function warmStartupStorage() {
  if (warmDone) return Promise.resolve()
  if (warmPromise) return warmPromise

  warmPromise = (async () => {
    const keys = [
      TOKEN_KEY,
      USER_INFO_KEY,
      CHANNEL_KEY,
      BGM_KEY,
      SFX_KEY,
      TOUCH_DATE_KEY,
      'pun_changelog_suppress',
    ]
    const entries = await Promise.all(
      keys.map(async (key) => [key, await getStorageAsync(key)])
    )
    const data = Object.fromEntries(entries)
    for (const [key, val] of entries) {
      memStore.set(key, val)
    }
    applyWarmToGlobalCaches(data)
    warmDone = true
  })().catch((e) => {
    console.warn('[warmStartupStorage]', e)
    warmDone = true
  })

  return warmPromise
}

export function isStartupStorageWarm() {
  return warmDone
}

export function setPendingChannel(channel) {
  if (!channel || typeof channel !== 'string' || channel.length > 64) return
  pendingChannelMem = channel
  memStore.set(CHANNEL_KEY, channel)
  try {
    uni.setStorage({ key: CHANNEL_KEY, data: channel, fail() {} })
  } catch {
    /* noop */
  }
}

/** 读取待上报渠道（不消费）；启动未预热前不读 sync */
export function peekPendingChannel() {
  if (typeof pendingChannelMem === 'string' && pendingChannelMem) {
    return pendingChannelMem
  }
  if (pendingChannelMem === null) return ''
  const fromMem = memStore.get(CHANNEL_KEY)
  if (fromMem) {
    pendingChannelMem = String(fromMem)
    return pendingChannelMem
  }
  return ''
}

/** 消费并清空渠道 */
export function clearPendingChannel() {
  pendingChannelMem = null
  memStore.delete(CHANNEL_KEY)
  try {
    uni.removeStorage({ key: CHANNEL_KEY, fail() {} })
  } catch {
    /* noop */
  }
}

/**
 * @param {string} storageKey
 * @returns {object | null}
 */
export function getJsonStorageCached(storageKey) {
  if (changelogCache.has(storageKey)) {
    return changelogCache.get(storageKey)
  }
  return null
}

/**
 * @param {string} storageKey
 * @param {object} value
 */
export function setJsonStorageCached(storageKey, value) {
  changelogCache.set(storageKey, value)
  memStore.set(storageKey, JSON.stringify(value))
  try {
    uni.setStorage({ key: storageKey, data: JSON.stringify(value), fail() {} })
  } catch {
    /* noop */
  }
}
