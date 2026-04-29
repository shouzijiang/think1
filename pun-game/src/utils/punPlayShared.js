import { api } from './api'
import { playErrorOnce } from './gameAudio'

/** 分享后判定「分享成功」所需的最短后台停留时长（ms） */
export const SHARE_SUCCESS_THRESHOLD_MS = 2000

/** @type {Map<string, { hintText: string, step: number, maxSteps: number, isComplete: boolean }>} */
const punHintCache = new Map()

/**
 * 同一题目的缓存 key（支持 beginner/mid/xhs/battle）。
 * @param {Record<string, unknown>} payload
 */
function getPunHintCacheKey(payload) {
  return JSON.stringify({
    gameTier: String(payload.gameTier || ''),
    level: Number(payload.level || 0),
    roomId: String(payload.roomId || ''),
    questionIndex: Number(payload.questionIndex ?? -1),
  })
}

/**
 * 槽位是否显示为错误态（与 submit 返回的 feedback 一致）
 * @param {Array<{ isCorrect?: boolean }>|null|undefined} feedbackList
 * @param {number} index
 */
export function punIsSlotError(feedbackList, index) {
  const fb = feedbackList && feedbackList[index]
  return !!(fb && fb.isCorrect === false)
}

/**
 * 答错后的统一反馈：错误音效、槽位抖动、toast，动画结束后执行 reset。
 * @param {{ value: boolean }} slotShakeRef Vue ref
 * @param {() => void} reset 清空输入与 feedback 等
 */
export function punScheduleWrongAnswerReset(slotShakeRef, reset) {
  playErrorOnce()
  slotShakeRef.value = true
  uni.showToast({ title: '再想想～', icon: 'none' })
  setTimeout(() => {
    slotShakeRef.value = false
    setTimeout(() => reset(), 200)
  }, 600)
}

/**
 * 是否与后端「本题提示已用尽」一致
 * @param {unknown} err
 */
export function punIsHintExhaustedError(err) {
  const m =
    err && typeof err === 'object' && err !== null && 'message' in err
      ? String(/** @type {{ message?: string }} */ (err).message)
      : String(err ?? '')
  return m.includes('本题提示已用尽')
}

/**
 * `punRevealHintWithModal` 失败时的 toast：刚看完激励视频且本题步数已满时提示已领到次数，否则展示接口错误。
 * @param {unknown} err
 * @param {boolean} afterRewardVideo 是否本次流程里刚通过激励视频领到 +1（仅微信小程序 0 次数路径）
 */
export function punToastRevealHintAfterError(err, afterRewardVideo) {
  if (afterRewardVideo && punIsHintExhaustedError(err)) {
    uni.showToast({ title: '已获得 1 次答案', icon: 'success' })
    return
  }
  const msg =
    err && typeof err === 'object' && err !== null && 'message' in err
      ? String(/** @type {{ message?: string }} */ (err).message)
      : ''
  uni.showToast({ title: msg || '获取失败', icon: 'none' })
}

/**
 * 分步揭字：请求接口并用与全站闯关页一致的弹窗展示
 * @param {Record<string, unknown>} payload revealHint 请求体
 */
export async function punRevealHintWithModal(payload) {
  const cacheKey = getPunHintCacheKey(payload)
  try {
    const data = await api.revealHint(payload)
    const normalized = {
      hintText: String(data.hintText || ''),
      step: Number(data.step || 0),
      maxSteps: Number(data.maxSteps || 0),
      isComplete: !!data.isComplete,
      hintAnswerQuota: Number(data.hintAnswerQuota ?? 0),
    }
    punHintCache.set(cacheKey, normalized)
    return normalized
  } catch (err) {
    // 本题已用尽时，允许重复查看上次完整提示（不再抛错）
    if (punIsHintExhaustedError(err)) {
      const cached = punHintCache.get(cacheKey)
      if (cached) {
        return {
          ...cached,
          isComplete: true,
        }
      }
    }
    throw err
  }
}

/**
 * 读取指定题目的提示缓存（用于提示已用尽后重复查看）。
 * @param {Record<string, unknown>} payload revealHint 请求体
 */
export function punGetCachedHint(payload) {
  const cacheKey = getPunHintCacheKey(payload)
  return punHintCache.get(cacheKey) || null
}

/**
 * 跳关统一确认弹窗
 * @param {number} cost
 * @returns {Promise<boolean>}
 */
export async function punConfirmSkipLevel(cost = 2) {
  const modal = await uni.showModal({
    title: '确认跳关',
    content: `为了防止恶意跳关，跳关将扣除${cost}次查看答案次数，是否继续？(可反馈问题，采纳后返还次数)`,
    confirmText: '确认跳关',
    cancelText: '取消',
  })
  return !!modal.confirm
}
