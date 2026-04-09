import { ref, computed, nextTick } from 'vue'

/**
 * 中文谐音闯关：隐藏 input + 按汉字填充槽位 + 光标槽位（与 playMid / playXhs / battle 一致）
 *
 * @param {object} p
 * @param {import('vue').Ref<number>} p.answerLen
 * @param {import('vue').Ref<string[]>} p.answerChars
 * @param {import('vue').Ref<string>} p.answerInputValue
 * @param {() => void} p.onFilled 长度凑满时触发（内部可调用 checkAnswer）
 */
export function usePunHanAnswerInput({ answerLen, answerChars, answerInputValue, onFilled }) {
  const inputRef = ref(null)
  const inputFocused = ref(false)

  const caretIndex = computed(() => {
    if (!inputFocused.value) return -1
    const n = answerChars.value.length
    if (n >= answerLen.value) return -1
    return n
  })

  function onInputFocus() {
    inputFocused.value = true
  }

  function onInputBlur() {
    inputFocused.value = false
  }

  function focusInput() {
    nextTick(() => {
      const el = inputRef.value
      if (el && typeof el.focus === 'function') el.focus()
    })
  }

  function onAnswerInput(e) {
    const raw = (e.detail && e.detail.value) || ''
    // 不对原始输入做 maxlength 截断，否则拼音输入法会在字母长度达到 answerLen 时卡住
    answerInputValue.value = raw

    const hasHan = /[\u4e00-\u9fff]/.test(raw)
    if (!hasHan) {
      answerChars.value = []
      return
    }

    const parts = Array.from(raw).slice(0, answerLen.value)
    answerChars.value = parts
    if (parts.length === answerLen.value) onFilled()
  }

  return {
    inputRef,
    inputFocused,
    caretIndex,
    onInputFocus,
    onInputBlur,
    focusInput,
    onAnswerInput,
  }
}
