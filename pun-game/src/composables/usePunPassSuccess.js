import { ref, onUnmounted } from 'vue'
import { playCongratsOnce } from '../utils/gameAudio'

/**
 * 通关成功：祝贺音效 + 短时全屏动效。
 * @param {object} opts
 * @param {number} opts.durationMs 从「关闭计时起点」起再展示多久后执行 onAfter（毫秒）
 * @param {() => (void|Promise<unknown>)} [opts.afterPrepare] 动效立刻出现后执行；若返回 Promise，则在其 settle 后再开始 durationMs 倒计时（与 playXhs 先拉下一关再 1200ms 一致）
 * @param {(prepResult?: unknown) => void} [opts.onAfter] 收起动效后回调，若有 afterPrepare 则传入其决议值
 * @returns {{ showSuccess: import('vue').Ref<boolean>, runPassSuccess: (opts) => void }}
 */
export function usePunPassSuccess() {
  const showSuccess = ref(false)
  let hideTimer = null
  let pendingAfter = null

  function runPassSuccess({ durationMs, onAfter, afterPrepare, manualClose = false }) {
    playCongratsOnce()
    showSuccess.value = true
    if (hideTimer) {
      clearTimeout(hideTimer)
      hideTimer = null
    }
    pendingAfter = null

    const armCloseTimer = (prepResult) => {
      if (manualClose) {
        pendingAfter = () => {
          showSuccess.value = false
          if (typeof onAfter === 'function') onAfter(prepResult)
          pendingAfter = null
        }
        return
      }
      hideTimer = setTimeout(() => {
        showSuccess.value = false
        hideTimer = null
        if (typeof onAfter === 'function') onAfter(prepResult)
      }, durationMs)
    }

    if (typeof afterPrepare === 'function') {
      try {
        const r = afterPrepare()
        if (r && typeof r.then === 'function') {
          r.then(armCloseTimer).catch(() => armCloseTimer(undefined))
        } else {
          armCloseTimer(r)
        }
      } catch {
        armCloseTimer(undefined)
      }
    } else {
      armCloseTimer(undefined)
    }
  }

  function confirmPassSuccess() {
    if (hideTimer) {
      clearTimeout(hideTimer)
      hideTimer = null
    }
    if (typeof pendingAfter === 'function') {
      pendingAfter()
      return true
    }
    return false
  }

  onUnmounted(() => {
    if (hideTimer) clearTimeout(hideTimer)
    pendingAfter = null
  })

  return { showSuccess, runPassSuccess, confirmPassSuccess }
}
