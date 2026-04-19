import { api } from './api'
import { playErrorOnce } from './gameAudio'

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
  const data = await api.revealHint(payload)
  uni.showModal({
    title: data.isComplete ? '已全部提示' : `提示 (${data.step}/${data.maxSteps})`,
    content: data.hintText,
    showCancel: false,
  })
  return data
}
