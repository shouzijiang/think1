import { ref, computed } from 'vue'
import { getUserInfo } from '../utils/auth'
import { api } from '../utils/api'
import { useNavBar } from './useNavBar'

export function useUserProfileNav() {
  const { menuButtonHeight } = useNavBar()
  const userInfo = ref(null)

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

  function imageToBase64(filePath) {
    return new Promise((resolve, reject) => {
      // #ifdef MP-WEIXIN
      const fs = uni.getFileSystemManager()
      fs.readFile({
        filePath,
        encoding: 'base64',
        success: (res) => {
          const ext = (filePath.split('.').pop() || 'jpg').toLowerCase()
          const mime = ext === 'png' ? 'png' : 'jpeg'
          resolve(`data:image/${mime};base64,${res.data}`)
        },
        fail: (err) => {
          console.error('读取头像失败', err)
          reject(new Error('读取头像失败'))
        },
      })
      // #endif
      // #ifndef MP-WEIXIN
      reject(new Error('仅微信小程序支持'))
      // #endif
    })
  }

  async function onChooseAvatar(e) {
    // #ifdef MP-WEIXIN
    const avatarUrl = e.detail.avatarUrl
    if (!avatarUrl) return
    const currentNickname = userInfo.value?.nickname || '微信用户'
    try {
      uni.showLoading({ title: '上传中…', mask: true })
      const avatarBase64 = await imageToBase64(avatarUrl)
      await api.updateUserInfo({ nickname: currentNickname, avatar: avatarBase64 })
      const updated = { ...userInfo.value, avatar: avatarUrl }
      uni.setStorageSync('userInfo', updated)
      userInfo.value = updated
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
    navBtnSizeStyle,
    loadUserInfo,
    onChooseAvatar,
    handleNicknameBlur,
    handleNicknameConfirm,
  }
}
