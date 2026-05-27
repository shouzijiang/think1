<script>
import { syncBgmByCurrentPage } from './utils/gameAudio'

/** 小程序端：右上角菜单展示「转发」相关入口（需页面实现对应生命周期） */
function enableMiniProgramShareMenu() {
  if (typeof uni.showShareMenu !== 'function') return
  let menus = ['shareAppMessage', 'shareTimeline']
  try {
    const platform = (uni.getAppBaseInfo?.() ?? uni.getSystemInfoSync()).uniPlatform || ''
    // 抖音小程序仅支持 share / record / screenShot
    if (platform === 'mp-toutiao') {
      menus = ['share']
    }
  } catch {}
  uni.showShareMenu({
    withShareTicket: true,
    menus,
    fail(err) {
      console.warn('[showShareMenu]', err)
    },
  })
}

let routeAudioHookInstalled = false
let appLaunchAt = 0
const APP_SHOW_BGM_SYNC_DELAY_MS = 2000

function scheduleSyncBgmByCurrentPage() {
  setTimeout(() => {
    syncBgmByCurrentPage()
  }, 0)
}

function installRouteAudioHook() {
  if (routeAudioHookInstalled || typeof uni === 'undefined') return
  routeAudioHookInstalled = true
  const methodNames = ['navigateTo', 'redirectTo', 'reLaunch', 'switchTab', 'navigateBack']
  methodNames.forEach((methodName) => {
    const raw = uni[methodName]
    if (typeof raw !== 'function') return
    uni[methodName] = function patchedUniRoute(options = {}) {
      const nextOptions = options && typeof options === 'object' ? { ...options } : {}
      const rawSuccess = typeof nextOptions.success === 'function' ? nextOptions.success : null
      const rawFail = typeof nextOptions.fail === 'function' ? nextOptions.fail : null
      const rawComplete = typeof nextOptions.complete === 'function' ? nextOptions.complete : null
      nextOptions.success = (...args) => {
        try {
          rawSuccess && rawSuccess(...args)
        } finally {
          scheduleSyncBgmByCurrentPage()
        }
      }
      nextOptions.fail = (...args) => {
        rawFail && rawFail(...args)
      }
      nextOptions.complete = (...args) => {
        rawComplete && rawComplete(...args)
      }
      return raw.call(uni, nextOptions)
    }
  })
}

let cloudInited = false

function initWxCloudLazy() {
  if (cloudInited || typeof wx === 'undefined' || typeof wx.cloud?.init !== 'function') return
  cloudInited = true
  try {
    wx.cloud.init({
      env: 'aaa-d6g7scjdwfbee4d24',
    })
  } catch {
    /* noop */
  }
}

export default {
  onLaunch: function () {
    console.log('App Launch')
    appLaunchAt = Date.now()

    // 小程序更新检测
    if (uni.canIUse('getUpdateManager')) {
      const updateManager = uni.getUpdateManager()

      updateManager.onCheckForUpdate(function (res) {
        // 有新版本
        if (res.hasUpdate) {
          updateManager.onUpdateReady(function () {
            uni.showModal({
              title: '更新提示',
              content: '新版本已经准备好，是否重启应用？',
              success: function (res) {
                if (res.confirm) {
                  // 重启并应用新版本
                  updateManager.applyUpdate()
                }
              }
            })
          })

          updateManager.onUpdateFailed(function () {
            uni.showModal({
              title: '提示',
              content: '新版本下载失败，请删除小程序重新打开',
              showCancel: false
            })
          })
        }
      })
    }
    enableMiniProgramShareMenu()
    installRouteAudioHook()
    // 登录与 BGM 由首页 onShow 处理，避免阻塞 App.onLaunch 首屏
    setTimeout(initWxCloudLazy, 0)
  },
  onShow: function () {
    console.log('App Show')
    enableMiniProgramShareMenu()
    // 冷启动前 2s 由首页延迟播 BGM，避免与首屏抢资源
    if (Date.now() - appLaunchAt >= APP_SHOW_BGM_SYNC_DELAY_MS) {
      scheduleSyncBgmByCurrentPage()
    }
  },
  onHide: function () {
    console.log('App Hide')
  },
}
</script>

<style lang="scss">
@use '@/styles/page-theme.scss' as *;

page {
  @include pt-font-body;
  color: $pt-text;
}
</style>
