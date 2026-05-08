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

    // 提取有效字符：汉字 + 英文字母（忽略数字、标点、拼音中间态的纯字母串中的组合状态）
    const validChars = Array.from(raw).filter(ch => /[\u4e00-\u9fffa-zA-Z]/.test(ch))

    if (validChars.length === 0) {
      answerChars.value = []
      return
    }

    // 英文字母统一转大写展示，汉字保持原样
    const parts = validChars.slice(0, answerLen.value).map(ch =>
      /[a-zA-Z]/.test(ch) ? ch.toUpperCase() : ch
    )
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
