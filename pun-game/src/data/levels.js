import { request } from '../utils/request'

/**
 * 关卡与题目数据（历史功能，不动）
 */
export const LEVELS_PER_PAGE = 40

const STORAGE_CURRENT = 'pun_game_current_level'
const STORAGE_PASSED = 'pun_game_passed_levels'

/** 内存缓存，避免重复 getStorageSync */
let currentLevelCache = null
let currentLevelCacheLoaded = false
let passedLevelsCache = null
let passedLevelsCacheLoaded = false

export function getCurrentLevel() {
  if (currentLevelCacheLoaded) return currentLevelCache
  try {
    const raw = uni.getStorageSync(STORAGE_CURRENT)
    if (raw === '' || raw === null || raw === undefined) {
      currentLevelCache = 1
    } else {
      const v = parseInt(String(raw), 10)
      currentLevelCache = Number.isFinite(v) && v >= 1 ? v : 1
    }
  } catch {
    currentLevelCache = 1
  }
  currentLevelCacheLoaded = true
  return currentLevelCache
}

export function setCurrentLevel(level) {
  const v = parseInt(String(level), 10)
  const safe = Number.isFinite(v) && v >= 1 ? v : 1
  currentLevelCache = safe
  currentLevelCacheLoaded = true
  try {
    uni.setStorage({ key: STORAGE_CURRENT, data: String(safe), fail() {} })
  } catch (e) {}
}

export function getPassedLevels() {
  if (passedLevelsCacheLoaded) return passedLevelsCache
  try {
    const raw = uni.getStorageSync(STORAGE_PASSED)
    if (!raw) {
      passedLevelsCache = []
    } else {
      const arr = JSON.parse(raw)
      passedLevelsCache = Array.isArray(arr) ? arr : []
    }
  } catch {
    passedLevelsCache = []
  }
  passedLevelsCacheLoaded = true
  return passedLevelsCache
}

export function addPassedLevel(level) {
  const passed = getPassedLevels()
  if (passed.includes(level)) return
  passed.push(level)
  passed.sort((a, b) => a - b)
  try {
    uni.setStorage({ key: STORAGE_PASSED, data: JSON.stringify(passed), fail() {} })
  } catch (e) {}
}

export function isLevelPassed(level) {
  return getPassedLevels().includes(level)
}

const LEVEL_DATA_BASE = 'https://sofun.online/static/punGame/issue'
/** 中级题库总表 */
const MID_ISSUE_URL = 'https://sofun.online/static/punGame/issue2.json'
/** 中级题目图片：w{level}-1 为上图，w{level}-2 为下图 */
const MID_IMAGE_BASE = 'https://sofun.online/static/punGame/img2'
const MID_IMAGE_BASE2 = 'https://static-cos.sofun.online/img2'
/** 小红书专辑题库 */
const XHS_ISSUE_URL = 'https://sofun.online/static/punGame/issue3.json'
/** 小红书专辑题目图片：{level}.webp */
const XHS_IMAGE_BASE = 'https://static2.sofun.online'
const XHS_IMAGE_BASE2 = 'https://static-cos.sofun.online'

const FALLBACK_PUZZLE = {
  hintText: '题库加载失败，请稍后重试',
  topCaption: '',
  bottomCaption: '',
  keywordHint: '',
  wordArray: ['测', '试', '题'],
  answerLength: 3,
  imageUrl: '',
  imageUrlTop: '',
  imageUrlBottom: '',
  isReviewMode: false,
  answer: '',
}

let midIssueCache = null
let midIssuePromise = null
let midMaxLevel = null
let midLevels = null
let xhsIssueCache = null
let xhsIssuePromise = null
let xhsLevels = null

export function normalizePuzzle(data) {
  const answerLength = Math.max(1, parseInt(data.answerLength, 10) || 3)
  return {
    hintText: data.hintText || '',
    topCaption: data.topCaption || '',
    bottomCaption: data.bottomCaption || '',
    keywordHint: data.keywordHint || data.category || '',
    wordArray: Array.isArray(data.wordArray) ? data.wordArray : [],
    answerLength,
    imageUrl: data.imageUrl || '',
    imageUrlTop: data.imageUrlTop || data.image_url_top || '',
    imageUrlBottom: data.imageUrlBottom || data.image_url_bottom || '',
    isReviewMode: !!data.isReviewMode,
    answer: data.answer != null ? String(data.answer).trim() : '',
    author: data.author != null ? String(data.author).trim() : '',
  }
}

/** 初级关卡题目 */
export function getLevelPuzzle(levelNum) {
  const n = parseInt(String(levelNum), 10)
  const safe = Number.isFinite(n) && n >= 1 ? n : 1
  const url = LEVEL_DATA_BASE + '/' + safe + '.json'
  return request({ url, method: 'GET' })
    .then((res) => (res.data && normalizePuzzle(res.data)))
    .catch(() => ({ ...FALLBACK_PUZZLE }))
}

function fetchMidIssues() {
  if (midIssueCache) return Promise.resolve(midIssueCache)
  if (midIssuePromise) return midIssuePromise

  midIssuePromise = request({ url: MID_ISSUE_URL, method: 'GET' })
    .then((res) => {
      const list = Array.isArray(res.data) ? res.data : []
      midIssueCache = list
      const levels = list
        .map((it) => (it && it.level != null ? parseInt(it.level, 10) : NaN))
        .filter((n) => Number.isFinite(n))
        .sort((a, b) => a - b)

      midLevels = Array.from(new Set(levels))
      const max = midLevels.length ? midLevels[midLevels.length - 1] : 0
      midMaxLevel = max || 0
      return list
    })
    .catch(() => {
      midIssueCache = []
      midMaxLevel = 0
      midLevels = []
      return []
    })

  return midIssuePromise
}

export function fetchXhsIssues() {
  if (xhsIssueCache) return Promise.resolve(xhsIssueCache)
  if (xhsIssuePromise) return xhsIssuePromise

  xhsIssuePromise = request({ url: XHS_ISSUE_URL, method: 'GET' })
    .then((res) => {
      const list = Array.isArray(res.data) ? res.data : []
      xhsIssueCache = list
      const levels = list
        .map((it) => (it && it.level != null ? parseInt(it.level, 10) : NaN))
        .filter((n) => Number.isFinite(n) && n > 0)
        .sort((a, b) => a - b)
      xhsLevels = Array.from(new Set(levels))
      return list
    })
    .catch(() => {
      xhsIssueCache = []
      xhsLevels = []
      return []
    })

  return xhsIssuePromise
}

export function getMidMaxLevel() {
  return midMaxLevel || 0
}

export function getMidLevelList() {
  return Array.isArray(midLevels) ? midLevels : []
}

export function getXhsLevelList() {
  return Array.isArray(xhsLevels) ? xhsLevels : []
}

export async function loadMidLevelList() {
  await fetchMidIssues()
  return getMidLevelList()
}

export async function loadXhsLevelList() {
  await fetchXhsIssues()
  return getXhsLevelList()
}

export function pickMidLevelFromProgress(data, orderedLevels) {
  const list = Array.isArray(orderedLevels) ? orderedLevels : getMidLevelList()
  if (!data) return list.length ? list[0] : null

  const lv = data.currentLevel != null ? Number(data.currentLevel) : null
  if (Number.isFinite(lv) && list.includes(lv)) return lv

  return list.length ? list[0] : null
}

export function pickXhsLevelFromProgress(data, orderedLevels) {
  const list = Array.isArray(orderedLevels) ? orderedLevels : getXhsLevelList()
  if (!data) return list.length ? list[0] : null

  const lv = data.currentLevel != null ? Number(data.currentLevel) : null
  if (Number.isFinite(lv) && list.includes(lv)) return lv
  return list.length ? list[0] : null
}

export function getMidNextLevel(levelNum) {
  const current = parseInt(levelNum, 10)
  return fetchMidIssues().then(() => {
    const levels = getMidLevelList()
    const idx = levels.indexOf(current)
    if (idx < 0) return null
    return idx + 1 < levels.length ? levels[idx + 1] : null
  })
}

export function getXhsNextLevel(levelNum) {
  const current = parseInt(levelNum, 10)
  return fetchXhsIssues().then(() => {
    const levels = getXhsLevelList()
    const idx = levels.indexOf(current)
    if (idx < 0) return null
    return idx + 1 < levels.length ? levels[idx + 1] : null
  })
}

export function getMidLevelImageUrls(levelNum) {
  const lv = parseInt(levelNum, 10)
  if (!Number.isFinite(lv) || lv < 0) return null
  return {
    imageUrlTop: MID_IMAGE_BASE + '/w' + lv + '-1.png',
    imageUrlBottom: MID_IMAGE_BASE + '/w' + lv + '-2.png',
  }
}

export function getXhsLevelImageUrl(levelNum) {
  const lv = parseInt(levelNum, 10)
  if (!Number.isFinite(lv) || lv <= 0) return ''
  return XHS_IMAGE_BASE + '/' + lv + '.webp'
}

export function prefetchImageUrls(urls, concurrency = 4) {
  const list = [...new Set((urls || []).filter(Boolean))]
  if (!list.length) return Promise.resolve()
  if (typeof uni === 'undefined' || typeof uni.getImageInfo !== 'function') {
    return Promise.resolve()
  }
  const limit = Math.max(1, parseInt(concurrency, 10) || 4)
  async function run() {
    for (let i = 0; i < list.length; i += limit) {
      const chunk = list.slice(i, i + limit)
      await Promise.all(
        chunk.map(
          (url) =>
            new Promise((resolve) => {
              uni.getImageInfo({
                src: url,
                success: () => resolve(),
                fail: () => resolve(),
                complete: () => resolve(),
              })
            })
        )
      )
    }
  }
  return run().catch(() => {})
}

export function prefetchNextMidLevelImages(currentLevelNum) {
  return getMidNextLevel(currentLevelNum)
    .then((next) => {
      if (next == null) return
      const imgs = getMidLevelImageUrls(next)
      if (!imgs) return
      return prefetchImageUrls([imgs.imageUrlTop, imgs.imageUrlBottom], 2)
    })
    .catch((e) => {
      console.warn('[prefetchNextMidLevelImages]', e)
    })
}

export function prefetchNextXhsLevelImage(currentLevelNum) {
  return getXhsNextLevel(currentLevelNum)
    .then((next) => {
      if (next == null) return
      const img = getXhsLevelImageUrl(next)
      if (!img) return
      return prefetchImageUrls([img], 1)
    })
    .catch((e) => {
      console.warn('[prefetchNextXhsLevelImage]', e)
    })
}

export function prefetchBattleXhsImages(levelIds) {
  const ids = Array.isArray(levelIds) ? levelIds : []
  const urls = []
  for (const id of ids) {
    const img = getXhsLevelImageUrl(id)
    if (img) urls.push(img)
  }
  return prefetchImageUrls(urls, 4)
}

export function prefetchBattleMidImages(levelIds) {
  const ids = Array.isArray(levelIds) ? levelIds : []
  const urls = []
  for (const id of ids) {
    const imgs = getMidLevelImageUrls(id)
    if (imgs) {
      if (imgs.imageUrlTop) urls.push(imgs.imageUrlTop)
      if (imgs.imageUrlBottom) urls.push(imgs.imageUrlBottom)
    }
  }
  return prefetchImageUrls(urls, 4)
}

export function getMidLevelPuzzle(levelNum) {
  const lv = parseInt(levelNum, 10)
  return fetchMidIssues().then((list) => {
    const item = list.find((x) => x && parseInt(x.level, 10) === lv)
    if (!item) {
      throw new Error('题目(level=' + lv + ')未找到')
    }

    const answerLength = Math.max(1, parseInt(item.answerLength, 10) || 3)
    const imgs = getMidLevelImageUrls(lv)
    return normalizePuzzle({
      hintText: item.tips || '',
      topCaption: item.question ? '这是' + item.question : '',
      bottomCaption: '这是' + '_'.repeat(answerLength),
      keywordHint: item.answerType || '',
      wordArray: [],
      answerLength,
      imageUrlTop: imgs ? imgs.imageUrlTop : '',
      imageUrlBottom: imgs ? imgs.imageUrlBottom : '',
      isReviewMode: false,
      answer: item.answer != null ? String(item.answer).trim() : '',
    })
  })
}

export function getXhsLevelPuzzle(levelNum) {
  const lv = parseInt(levelNum, 10)
  return fetchXhsIssues().then((list) => {
    const item = list.find((x) => x && parseInt(x.level, 10) === lv)
    if (!item) {
      throw new Error('题目(level=' + lv + ')未找到')
    }

    const answerLength = Math.max(1, parseInt(item.answerLength, 10) || 3)
    const imageUrl = getXhsLevelImageUrl(lv)
    return normalizePuzzle({
      hintText: '',
      topCaption: '',
      bottomCaption: '',
      keywordHint: item.answerType || '',
      wordArray: [],
      answerLength,
      imageUrl,
      imageUrlTop: imageUrl,
      imageUrlBottom: '',
      isReviewMode: false,
      answer: item.answer != null ? String(item.answer).trim() : '',
      author: item.author != null ? String(item.author).trim() : '',
    })
  })
}
