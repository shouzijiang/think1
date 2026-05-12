<script>
import { wechatLogin } from './utils/auth'

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

export default {
  onLaunch: async function () {
    console.log('App Launch')
    // 小程序登录（微信/抖音）
    await wechatLogin()

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
  },
  onShow: function () {
    console.log('App Show')
    enableMiniProgramShareMenu()
  },
  onHide: function () {
    console.log('App Hide')
  },
}
</script>

<style>
/*每个页面公共css */
</style>
