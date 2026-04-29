import { ref, computed } from 'vue'
import { getUserInfo } from '../utils/auth'
import { api } from '../utils/api'
import { useNavBar } from './useNavBar'

export function useUserProfileNav() {
  const { menuButtonHeight } = useNavBar()
  const userInfo = ref(null)
  const avatarTs = ref(Date.now())

  const navBtnSizeStyle = computed(() => {
    const h = menuButtonHeight.value
    return {
      width: `${h}px`,
      height: `${h}px`,
    }
  })

  function loadUserInfo() {
    const info = getUserInfo()
    userInfo.value = info || { nickname: '', avatar: '', user_id: null, openid: '' }
  }

  async function onChooseAvatar(e) {
    // #ifdef MP-WEIXIN
    const avatarUrl = e.detail.avatarUrl
    if (!avatarUrl) return
    try {
      uni.showLoading({ title: '上传中…', mask: true })
      // 直接把微信临时路径上传到 COS，后端落库 URL
      const result = await api.uploadAvatar(avatarUrl)
      const cosUrl = result.url
      const updated = { ...userInfo.value, avatar: cosUrl }
      uni.setStorageSync('userInfo', updated)
      userInfo.value = updated
      avatarTs.value = Date.now()
      uni.hideLoading()
      uni.showToast({ title: '头像已更新', icon: 'success' })
    } catch (err) {
      uni.hideLoading()
      uni.showToast({ title: err.message || '更新失败', icon: 'none' })
    }
    // #endif
  }

  function handleNicknameBlur(e) {
    saveNickname(e.detail.value)
  }

  function handleNicknameConfirm(e) {
    saveNickname(e.detail.value)
  }

  async function saveNickname(nickname) {
    if (!userInfo.value) return
    const trimmed = (nickname || '').trim() || '微信用户'
    if (trimmed === userInfo.value.nickname) return
    const currentAvatar = userInfo.value.avatar || ''
    try {
      await api.updateUserInfo({ nickname: trimmed, avatar: currentAvatar })
      const updated = { ...userInfo.value, nickname: trimmed }
      uni.setStorageSync('userInfo', updated)
      userInfo.value = updated
      uni.showToast({ title: '昵称已保存', icon: 'success' })
    } catch (err) {
      uni.showToast({ title: err.message || '保存失败', icon: 'none' })
      loadUserInfo()
    }
  }

  return {
    userInfo,
    avatarTs,
    navBtnSizeStyle,
    loadUserInfo,
    onChooseAvatar,
    handleNicknameBlur,
    handleNicknameConfirm,
  }
}
