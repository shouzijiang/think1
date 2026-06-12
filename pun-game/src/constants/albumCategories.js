/**
 * Album category configuration
 * 专辑元数据由服务端 pun_album_category 表统一管理，前端通过 API 动态加载。
 * 本文件仅保留 answerType → slug 反向映射（供 albumLevels 过滤用）。
 */

import { api } from '../utils/api'

/** answerType → category slug 反向映射，loadAlbumCategories 会动态填充 */
export const ANSWER_TYPE_CATEGORY_MAP = {}

/**
 * 从服务端加载专辑分类（优先），失败时返回空数组。
 * 返回格式与 PunAlbumPicker 的 categories 兼容。
 *
 * @returns {Promise<Array<{slug:string, label:string, icon:string, subTypes:Array}>>}
 */
export async function loadAlbumCategories() {
  try {
    const data = await api.getAlbumCategories()
    if (data && Array.isArray(data.categories) && data.categories.length > 0) {
      // 重建 ANSWER_TYPE_CATEGORY_MAP
      for (const key of Object.keys(ANSWER_TYPE_CATEGORY_MAP)) {
        delete ANSWER_TYPE_CATEGORY_MAP[key]
      }
      const cats = data.categories.map((c) => {
        const types = Array.isArray(c.answerTypes) ? c.answerTypes : []
        const count = typeof c.totalCount === 'number' ? c.totalCount : 0
        for (const at of types) {
          ANSWER_TYPE_CATEGORY_MAP[at] = c.slug
        }
        return {
          slug: c.slug,
          label: c.label,
          icon: c.icon || '',
          subTypes: [{ type: types.join('/'), answerTypes: types, count }],
        }
      })
      return cats
    }
  } catch (e) {
    console.warn('loadAlbumCategories failed', e)
  }
  return []
}
