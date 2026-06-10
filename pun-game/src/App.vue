<script>
import { syncBgmByCurrentPage } from './utils/gameAudio'
import { warmStartupStorage } from './utils/storageCache'

/** 小程序端：右上角菜单展示「转发」相关入口（需页面实现对应生命周期） */
let cachedPlatform = null
function getPlatform() {
  if (cachedPlatform !== null) return cachedPlatform
  try {
    cachedPlatform = uni.getAppBaseInfo?.()?.uniPlatform || ''
  } catch {
    cachedPlatform = ''
  }
  return cachedPlatform
}

function enableMiniProgramShareMenu() {
  if (shareMenuEnabled || typeof uni.showShareMenu !== 'function') return
  let menus = ['shareAppMessage', 'shareTimeline']
  // 抖音小程序仅支持 share / record / screenShot
  if (getPlatform() === 'mp-toutiao') {
    menus = ['share']
  }
  uni.showShareMenu({
    withShareTicket: true,
    menus,
    success() {
      shareMenuEnabled = true
    },
    fail(err) {
      console.warn('[showShareMenu]', err)
    },
  })
}

let appLaunchAt = 0
const APP_SHOW_BGM_SYNC_DELAY_MS = 2000
let shareMenuEnabled = false

function scheduleSyncBgmByCurrentPage() {
  setTimeout(() => {
    syncBgmByCurrentPage()
  }, 0)
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
    // wx.onAppRouteDone 监听器已转由 pages/index/index 的 onLoad/onUnload 配对管理
    // 异步预热 storage，避免启动阶段 getStorageSync 阻塞
    setTimeout(() => {
      warmStartupStorage()
      initWxCloudLazy()
    }, 0)
  },
  onShow: function () {
    console.log('App Show')
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

/* ========== 全局滚动条美化 ========== */

/* Firefox */
html {
  scrollbar-width: thin;
  scrollbar-color: rgba(169, 201, 238, 0.45) transparent;
}

/* WebKit (Chrome / Safari / Edge) */
::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

::-webkit-scrollbar-track {
  background: transparent;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb {
  background: rgba(169, 201, 238, 0.45);
  border-radius: 3px;
  transition: background 0.25s ease;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(169, 201, 238, 0.7);
}

::-webkit-scrollbar-thumb:active {
  background: rgba(145, 213, 139, 0.55); /* $pt-mint */
}

::-webkit-scrollbar-corner {
  background: transparent;
}

page {
  @include pt-font-body;
  color: $pt-text;
}
</style>
