import { ref, computed } from 'vue'
import { getUserInfo, syncCachedUserInfo } from '../utils/auth'
import { api } from '../utils/api'
import { useNavBar } from './useNavBar'

const NICKNAME_MAX_LEN = 10

/** 按 Unicode 码点截断，正确处理中文、emoji 等多字节字符 */
function truncateNickname(str) {
  return [...String(str)].slice(0, NICKNAME_MAX_LEN).join('')
}

export function useUserProfileNav() {
  const { menuButtonHeight } = useNavBar()
  const userInfo = ref(null)
  /** 与 input 双向绑定；抖音/微信若不绑定草稿，blur 时 detail.value 可能拿不到修改后的昵称 */
  const nicknameDraft = ref('')
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
    nicknameDraft.value = userInfo.value.nickname || ''
  }

  async function updateAvatarFromPath(avatarUrl) {
    if (!avatarUrl) return
    if (!userInfo.value?.user_id) {
      loadUserInfo()
    }
    if (!userInfo.value?.user_id) {
      uni.showToast({ title: '请先登录后再换头像', icon: 'none' })
      return
    }
    try {
      uni.showLoading({ title: '上传中…', mask: true })
      // 直接把小程序临时路径上传到 COS，后端落库 URL
      const result = await api.uploadAvatar(avatarUrl)
      const cosUrl = result.url
      const updated = { ...userInfo.value, avatar: cosUrl }
      // 同步内存缓存 + uni storage，防止后续 saveNickname 读到旧头像
      syncCachedUserInfo({ avatar: cosUrl })
      uni.setStorage({ key: 'userInfo', data: updated, fail() {} })
      userInfo.value = updated
      avatarTs.value = Date.now()
      uni.hideLoading()
      uni.showToast({ title: '头像已更新', icon: 'success' })
    } catch (err) {
      uni.hideLoading()
      uni.showToast({ title: err.message || '更新失败', icon: 'none' })
    }
  }

  async function onChooseAvatar(e) {
    const avatarUrl = e?.detail?.avatarUrl
    if (!avatarUrl) return
    await updateAvatarFromPath(avatarUrl)
  }

  function pickTempImagePath(res) {
    if (!res) return ''
    const paths = res.tempFilePaths
    if (Array.isArray(paths) && paths[0]) return paths[0]
    const files = res.tempFiles
    if (Array.isArray(files) && files[0]) {
      const f = files[0]
      if (typeof f === 'string') return f
      if (f.path) return f.path
      if (f.tempFilePath) return f.tempFilePath
    }
    return ''
  }

  function isChooseImageCancel(err) {
    const msg = err && (err.errMsg || err.message || err)
    return typeof msg === 'string' && msg.includes('cancel')
  }

  /**
   * 抖音端：开发者工具里「相册+相机」「compressed」容易失败，优先仅相册 + original；其次 chooseMedia。
   */
  function invokeChooseImage(sourceType, sizeType) {
    return new Promise((resolve, reject) => {
      const onSuccess = (res) => {
        const p = pickTempImagePath(res)
        if (p) resolve(p)
        else reject(new Error('未获取到图片路径'))
      }
      const onFail = (err) => reject(err || new Error('chooseImage fail'))
      const opts = {
        count: 1,
        sourceType,
        success: onSuccess,
        fail: onFail,
      }
      if (Array.isArray(sizeType) && sizeType.length > 0) {
        opts.sizeType = sizeType
      }
      if (typeof tt !== 'undefined' && typeof tt.chooseImage === 'function') {
        tt.chooseImage(opts)
      } else {
        uni.chooseImage(opts)
      }
    })
  }

  function invokeChooseMedia() {
    return new Promise((resolve, reject) => {
      if (typeof uni.chooseMedia !== 'function') {
        reject(new Error('chooseMedia 不可用'))
        return
      }
      uni.chooseMedia({
        count: 1,
        mediaType: ['image'],
        sourceType: ['album', 'camera'],
        success: (res) => {
          const f = res.tempFiles && res.tempFiles[0]
          const p = f && (f.tempFilePath || f.path)
          if (p) resolve(p)
          else reject(new Error('未获取到图片路径'))
        },
        fail: reject,
      })
    })
  }

  function chooseAvatarByImagePicker() {
    const isTt = typeof tt !== 'undefined' && typeof tt.chooseImage === 'function'
    ;(async () => {
      /** @type {Array<() => Promise<string>>} */
      const attempts = []
      if (isTt) {
        attempts.push(
          () => invokeChooseImage(['album'], ['original', 'compressed']),
          () => invokeChooseImage(['album'], ['compressed']),
          () => invokeChooseImage(['album']),
          () => invokeChooseImage(['album', 'camera'], ['original', 'compressed']),
        )
      } else {
        attempts.push(
          () => invokeChooseImage(['album', 'camera'], ['compressed']),
          () => invokeChooseImage(['album'], ['compressed']),
        )
      }

      for (const run of attempts) {
        try {
          const filePath = await run()
          if (filePath) {
            await updateAvatarFromPath(filePath)
            return
          }
        } catch (e) {
          if (isChooseImageCancel(e)) return
        }
      }

      try {
        const filePath = await invokeChooseMedia()
        await updateAvatarFromPath(filePath)
      } catch (e) {
        if (isChooseImageCancel(e)) return
        const hint = String((e && e.errMsg) || e || '').trim()
        uni.showToast({
          title: hint.length > 24 ? '选择头像失败，请用真机或在开发者工具选相册图片' : hint || '选择头像失败',
          icon: 'none',
        })
      }
    })()
  }

  /** 抖音端 input 的 v-model 常不同步，必须用 @input 写回草稿 */
  function onNicknameDraftInput(e) {
    const v = e && e.detail ? e.detail.value : ''
    nicknameDraft.value = truncateNickname(v == null ? '' : v)
  }

  function handleNicknameBlur() {
    saveNickname(nicknameDraft.value)
  }

  function handleNicknameConfirm() {
    saveNickname(nicknameDraft.value)
  }

  async function saveNickname(nickname) {
    if (!userInfo.value || !userInfo.value.user_id) {
      // userInfo 尚未加载（极少数场景），尝试加载一次
      loadUserInfo()
    }
    if (!userInfo.value || !userInfo.value.user_id) {
      uni.showToast({ title: '请先登录后再保存昵称', icon: 'none' })
      return
    }
    const trimmed = truncateNickname((nickname || '').trim() || '用户')
    const prevRaw = userInfo.value.nickname
    const prev =
      prevRaw === undefined || prevRaw === null ? '' : String(prevRaw).trim()
    if (trimmed === prev) return
    try {
      // 仅发送昵称，不携带 avatar，避免因缓存不一致覆盖已更新的头像
      await api.updateUserInfo({ nickname: trimmed })
      syncCachedUserInfo({ nickname: trimmed })
      const updated = { ...userInfo.value, nickname: trimmed }
      uni.setStorage({ key: 'userInfo', data: updated, fail() {} })
      userInfo.value = updated
      nicknameDraft.value = trimmed
      uni.showToast({ title: '昵称已保存', icon: 'success' })
    } catch (err) {
      uni.showToast({ title: err.message || '保存失败', icon: 'none' })
      loadUserInfo()
    }
  }

  return {
    userInfo,
    nicknameDraft,
    avatarTs,
    navBtnSizeStyle,
    loadUserInfo,
    onChooseAvatar,
    chooseAvatarByImagePicker,
    onNicknameDraftInput,
    handleNicknameBlur,
    handleNicknameConfirm,
  }
}
