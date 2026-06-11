import { request } from '../utils/request'
import { ANSWER_TYPE_CATEGORY_MAP } from '../constants/albumCategories'
import {
  normalizePuzzle,
  fetchXhsIssues,
  getXhsLevelImageUrl,
  prefetchImageUrls,
} from './levels'

/* ===== 分类专辑 CDN 地址 ===== */
const CATEGORY_BASE_URL = 'https://sofun.online/static/punGame'
const CATEGORY_FILE_MAP = {
  character: CATEGORY_BASE_URL + '/issue3_character.json',
  food: CATEGORY_BASE_URL + '/issue3_food.json',
  fruit: CATEGORY_BASE_URL + '/issue3_fruit.json',
  dessert: CATEGORY_BASE_URL + '/issue3_dessert.json',
  city: CATEGORY_BASE_URL + '/issue3_city.json',
  landscape: CATEGORY_BASE_URL + '/issue3_landscape.json',
  idiom: CATEGORY_BASE_URL + '/issue3_idiom.json',
  plant: CATEGORY_BASE_URL + '/issue3_plant.json',
  christmas: CATEGORY_BASE_URL + '/issue3_christmas.json',
  newyear: CATEGORY_BASE_URL + '/issue3_newyear.json',
  zodiac: CATEGORY_BASE_URL + '/issue3_zodiac.json',
}

/* ===== 内部缓存 ===== */
let categoryCache = {}       // { character: [...], food: [...], ... }
let categoryPromise = {}     // { character: Promise, ... }
let categoryLevels = {}      // { character: [1,2,...], ... }
let filteredXhsCache = {}    // { '历史人物': [...], '美食,食物,菜名,菜品': [...], ... }
let filteredXhsLevels = {}   // { '历史人物': [1,2,...], ... }
let filteredXhsPromises = {} // { '历史人物': Promise, ... }

/* ===== 分类专辑加载 ===== */

/** 加载分类专辑 JSON（如 issue3_character.json） */
function fetchCategoryIssues(categorySlug) {
  if (categoryCache[categorySlug]) return Promise.resolve(categoryCache[categorySlug])
  if (categoryPromise[categorySlug]) return categoryPromise[categorySlug]

  const url = CATEGORY_FILE_MAP[categorySlug]
  if (!url) return Promise.resolve([])

  categoryPromise[categorySlug] = request({ url, method: 'GET' })
    .then((res) => {
      const list = Array.isArray(res.data) ? res.data : []
      categoryCache[categorySlug] = list
      const levels = list
        .map((it) => (it && it.level != null ? parseInt(it.level, 10) : NaN))
        .filter((n) => Number.isFinite(n) && n > 0)
        .sort((a, b) => a - b)
      categoryLevels[categorySlug] = Array.from(new Set(levels))
      return list
    })
    .catch(() => {
      categoryCache[categorySlug] = []
      categoryLevels[categorySlug] = []
      return []
    })

  return categoryPromise[categorySlug]
}

/** 按 answerType 加载并过滤题目列表（支持逗号分隔的多类型合并） */
function fetchFilteredXhsIssues(filterType) {
  const filterTypes = String(filterType).split(',').map(s => s.trim()).filter(Boolean)
  const cacheKey = filterTypes.join(',')
  if (!filterTypes.length) return fetchXhsIssues()

  if (filteredXhsCache[cacheKey]) return Promise.resolve(filteredXhsCache[cacheKey])
  if (filteredXhsPromises[cacheKey]) return filteredXhsPromises[cacheKey]

  // 用第一个类型查找类别 slug
  const categorySlug = ANSWER_TYPE_CATEGORY_MAP[filterTypes[0]]
  if (!categorySlug) {
    return fetchXhsIssues()
  }

  filteredXhsPromises[cacheKey] = fetchCategoryIssues(categorySlug).then(() => {
    const allEntries = categoryCache[categorySlug] || []
    const filterSet = new Set(filterTypes)
    const filtered = allEntries.filter((e) => filterSet.has(e.answerType))
    filteredXhsCache[cacheKey] = filtered

    const levels = filtered
      .map((e) => parseInt(e.level, 10))
      .filter((n) => Number.isFinite(n) && n > 0)
      .sort((a, b) => a - b)
    filteredXhsLevels[cacheKey] = levels
    return filtered
  }).catch(() => {
    filteredXhsCache[cacheKey] = []
    filteredXhsLevels[cacheKey] = []
    return []
  })

  return filteredXhsPromises[cacheKey]
}

/* ===== 导出的分类专辑 API ===== */

/** 同步获取已加载的分类过滤关卡列表 */
export function getFilteredXhsLevelListForType(filterType) {
  return Array.isArray(filteredXhsLevels[filterType]) ? filteredXhsLevels[filterType] : []
}

/** 异步加载分类过滤的关卡列表 */
export async function loadFilteredXhsLevelList(filterType) {
  await fetchFilteredXhsIssues(filterType)
  return getFilteredXhsLevelListForType(filterType)
}

/** 根据服务器进度，选择分类专辑的起始关卡 */
export function pickFilteredXhsLevelFromProgress(data, filterType) {
  const list = getFilteredXhsLevelListForType(filterType)
  if (!list.length) return null

  if (!data) return list[0]

  const lv = data.currentLevel != null ? Number(data.currentLevel) : null
  if (Number.isFinite(lv) && list.includes(lv)) return lv

  return list[0]
}

/** 获取分类专辑中某一关的题目数据 */
export function getFilteredXhsLevelPuzzle(levelNum, filterType) {
  const lv = parseInt(levelNum, 10)
  return fetchFilteredXhsIssues(filterType).then((list) => {
    const item = list.find((x) => x && parseInt(x.level, 10) === lv)
    if (!item) {
      throw new Error('题目(level=' + lv + ')在分类' + filterType + '中未找到')
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

/** 获取分类专辑中当前关卡的下一关 */
export function getFilteredXhsNextLevel(levelNum, filterType) {
  const current = parseInt(levelNum, 10)
  return fetchFilteredXhsIssues(filterType).then(() => {
    const levels = getFilteredXhsLevelListForType(filterType)
    const idx = levels.indexOf(current)
    if (idx < 0) return null
    return idx + 1 < levels.length ? levels[idx + 1] : null
  })
}

/** 预取分类专辑下一关图片 */
export function prefetchNextFilteredXhsLevelImage(levelNum, filterType) {
  return getFilteredXhsNextLevel(levelNum, filterType)
    .then((next) => {
      if (next == null) return
      const img = getXhsLevelImageUrl(next)
      if (!img) return
      return prefetchImageUrls([img], 1)
    })
    .catch((e) => {
      console.warn('[prefetchNextFilteredXhsLevelImage]', e)
    })
}
