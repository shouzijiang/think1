/**
 * 微信小程序全局 wx.on* 监听（仅 App 级注册一次，避免页面反复注册导致泄漏）
 *
 * 设计原则：
 * - App.onLaunch 安装一次全局监听
 * - 页面 onUnload 时仅移除特定 handler，不动其他库的监听
 * - 页面 onHide 不清除监听器（页面未销毁，App 级监听应持续生效）
 */
import { syncBgmByCurrentPage } from './gameAudio'

let routeDoneHandler = null
let installed = false

function canUseWx() {
  return typeof wx !== 'undefined'
}

function scheduleSyncBgmByCurrentPage() {
  setTimeout(() => {
    syncBgmByCurrentPage()
  }, 0)
}

/** App.onLaunch 调用一次：路由动画结束后同步 BGM */
export function installMpAppWxListeners() {
  // #ifdef MP-WEIXIN
  if (installed || !canUseWx()) return
  if (typeof wx.onAppRouteDone !== 'function') return
  installed = true

  routeDoneHandler = () => {
    scheduleSyncBgmByCurrentPage()
  }
  wx.onAppRouteDone(routeDoneHandler)
  // #endif
}

/**
 * 仅移除我们安装的 handler（保留其他库/框架的监听器）。
 * 由页面 onUnload 调用，确保页面销毁后 handler 完全清除，不留泄漏。
 * 注意：onHide 不应调用此函数——页面隐藏不代表销毁，监听器应持续生效。
 */
export function uninstallMpAppWxListeners() {
  // #ifdef MP-WEIXIN
  if (!canUseWx()) return

  if (routeDoneHandler && typeof wx.offAppRouteDone === 'function') {
    wx.offAppRouteDone(routeDoneHandler)
  }
  installed = false
  routeDoneHandler = null
  // #endif
}
