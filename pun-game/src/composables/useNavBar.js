import { ref } from 'vue'

export function useNavBar() {
  const statusBarHeight = ref(20)
  const navBarHeight = ref(44)
  const menuButtonHeight = ref(32)

  // #ifdef MP-WEIXIN
  try {
    const windowInfo = wx.getWindowInfo()
    const menuButtonInfo = uni.getMenuButtonBoundingClientRect()
    if (windowInfo && menuButtonInfo) {
      statusBarHeight.value = windowInfo.statusBarHeight
      navBarHeight.value = menuButtonInfo.height + (menuButtonInfo.top - windowInfo.statusBarHeight) * 2
      menuButtonHeight.value = menuButtonInfo.height
    }
  } catch (e) {}
  // #endif

  return {
    statusBarHeight,
    navBarHeight,
    menuButtonHeight
  }
}
