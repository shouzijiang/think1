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
