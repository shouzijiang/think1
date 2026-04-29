import { ref } from 'vue'

export function useNavBar() {
  const statusBarHeight = ref(20)
  const navBarHeight = ref(44)
  const menuButtonHeight = ref(32)

  // #ifdef MP-WEIXIN
  if (typeof uni.getSystemInfo === 'function') {
    uni.getSystemInfo({
      success: (systemInfo) => {
        try {
          const menuButtonInfo = uni.getMenuButtonBoundingClientRect()
          if (systemInfo && menuButtonInfo) {
            statusBarHeight.value = systemInfo.statusBarHeight
            navBarHeight.value = menuButtonInfo.height + (menuButtonInfo.top - systemInfo.statusBarHeight) * 2
            menuButtonHeight.value = menuButtonInfo.height
          }
        } catch (e) {
          // fallback
        }
      },
      fail: () => {
        // fallback to sync
        try {
          const systemInfo = uni.getSystemInfoSync()
          const menuButtonInfo = uni.getMenuButtonBoundingClientRect()
          if (systemInfo && menuButtonInfo) {
            statusBarHeight.value = systemInfo.statusBarHeight
            navBarHeight.value = menuButtonInfo.height + (menuButtonInfo.top - systemInfo.statusBarHeight) * 2
            menuButtonHeight.value = menuButtonInfo.height
          }
        } catch (e) {}
      }
    })
  }
  // #endif

  return {
    statusBarHeight,
    navBarHeight,
    menuButtonHeight
  }
}
