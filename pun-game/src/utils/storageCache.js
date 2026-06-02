/**
 * 启动阶段 storage 内存缓存，避免重复 getStorageSync 同一 key。
 */

const CHANNEL_KEY = 'pun_channel'

/** @type {string | null | undefined} undefined=未读 storage，null=无值 */
let pendingChannelMem

export function setPendingChannel(channel) {
  if (!channel || typeof channel !== 'string' || channel.length > 64) return
  pendingChannelMem = channel
  try {
    uni.setStorage({ key: CHANNEL_KEY, data: channel, fail() {} })
  } catch {
    /* noop */
  }
}

/** 读取待上报渠道（不消费） */
export function peekPendingChannel() {
  if (typeof pendingChannelMem === 'string' && pendingChannelMem) {
    return pendingChannelMem
  }
  if (pendingChannelMem === null) return ''
  try {
    const v = uni.getStorageSync(CHANNEL_KEY)
    pendingChannelMem = v ? String(v) : null
    return pendingChannelMem || ''
  } catch {
    pendingChannelMem = null
    return ''
  }
}

/** 消费并清空渠道 */
export function clearPendingChannel() {
  pendingChannelMem = null
  try {
    uni.removeStorage({ key: CHANNEL_KEY, fail() {} })
  } catch {
    /* noop */
  }
}

const changelogCache = new Map()

/**
 * @param {string} storageKey
 * @returns {object | null}
 */
export function getJsonStorageCached(storageKey) {
  if (changelogCache.has(storageKey)) {
    return changelogCache.get(storageKey)
  }
  let parsed = null
  try {
    const raw = uni.getStorageSync(storageKey)
    if (raw) {
      parsed = typeof raw === 'string' ? JSON.parse(raw) : raw
    }
  } catch {
    parsed = null
  }
  changelogCache.set(storageKey, parsed)
  return parsed
}

/**
 * @param {string} storageKey
 * @param {object} value
 */
export function setJsonStorageCached(storageKey, value) {
  changelogCache.set(storageKey, value)
  try {
    uni.setStorage({ key: storageKey, data: JSON.stringify(value), fail() {} })
  } catch {
    /* noop */
  }
}
