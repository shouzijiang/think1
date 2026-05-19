import { api } from '../../utils/api'
import { punConfirmSkipLevel } from '../utils/punPlayShared'

/**
 * 统一跳关逻辑（扣 2 次查看答案次数）
 *
 * @param {{
 *   mode: 'beginner' | 'mid' | 'xhs',
 *   levelRef: { value: number },
 *   hintAnswerQuotaRef: { value: number },
 *   skipLoadingRef: { value: boolean },
 *   loadingRefs?: Array<{ value: boolean }>,
 *   canSkip?: () => boolean,
 *   isValidNextLevel?: (n: number) => boolean,
 *   toNextUrl: (n: number) => string,
 * }} options
 */
export function usePunSkipLevel(options) {
  const {
    mode,
    levelRef,
    hintAnswerQuotaRef,
    skipLoadingRef,
    loadingRefs = [],
    canSkip,
    isValidNextLevel,
    toNextUrl,
  } = options

  async function onSkipLevel() {
    if (typeof canSkip === 'function' && !canSkip()) return
    if (skipLoadingRef.value) return
    if (loadingRefs.some((r) => !!r?.value)) return

    if ((hintAnswerQuotaRef.value || 0) < 2) {
      uni.showToast({ title: '查看答案次数不足，无法跳关', icon: 'none' })
      return
    }

    const confirmed = await punConfirmSkipLevel(2)
    if (!confirmed) return

    skipLoadingRef.value = true
    try {
      const data = await api.skipLevel({
        level: Number(levelRef.value || 0),
        gameTier: mode,
      })

      if (typeof data?.hintAnswerQuota === 'number') {
        hintAnswerQuotaRef.value = data.hintAnswerQuota
      }

      const nextLevel = data && data.nextLevel != null ? Number(data.nextLevel) : NaN
      const ok = typeof isValidNextLevel === 'function'
        ? !!isValidNextLevel(nextLevel)
        : Number.isFinite(nextLevel) && nextLevel > 0

      if (!ok) {
        uni.showToast({ title: '已经是最后一关啦', icon: 'none' })
        return
      }

      uni.redirectTo({ url: toNextUrl(nextLevel) })
    } catch (e) {
      uni.showToast({ title: e.message || '跳关失败', icon: 'none' })
    } finally {
      skipLoadingRef.value = false
    }
  }

  return {
    onSkipLevel,
  }
}
