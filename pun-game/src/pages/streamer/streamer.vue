<template>
  <view class="page">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
    </view>

    <PunPageNavBar
      :status-bar-height="statusBarHeight"
      :nav-bar-height="navBarHeight"
      :menu-button-height="menuButtonHeight"
      title="邀请好友赚收益"
      @left-click="back"
    />

    <!-- 邀请码区域 -->
    <view class="qr-card">
      <text class="qr-title">📡 你的专属邀请码</text>
      <text class="qr-desc">扫码进入即自动绑定</text>

      <view class="qr-wrap">
        <view v-if="qrLoading" class="qr-placeholder">
          <text class="qr-loading-text">生成中…</text>
        </view>
        <image
          v-else-if="qrBase64"
          class="qr-img"
          :src="qrTempPath || ('data:image/png;base64,' + qrBase64)"
          mode="aspectFit"
          show-menu-by-longpress
        />
        <view v-else class="qr-placeholder qr-placeholder--error">
          <text class="qr-loading-text">请生成您的专属邀请码</text>
        </view>
      </view>

      <view class="qr-channel-tag">
        <text class="qr-channel-label">你的ID</text>
        <text class="qr-channel-value">{{ userId || '加载中…' }}</text>
      </view>

      <view class="qr-actions">
        <button class="qr-btn" :disabled="qrLoading" @click="loadQrCode">
          {{ qrLoading ? '生成中…' : (qrBase64 ? '刷新二维码' : '生成专属二维码') }}
        </button>
        <button v-if="qrBase64" class="qr-btn qr-btn--save" @click="saveQrCode">
          保存到相册
        </button>
      </view>
    </view>

    <!-- 数据统计 -->
    <view class="stats-card">
      <view class="stats-header">
        <text class="stats-title">📊 邀请数据</text>
        <text class="stats-refresh" @click="loadStats">刷新</text>
      </view>

      <view v-if="statsLoading" class="stats-loading">
        <text>加载中…</text>
      </view>
      <template v-else>
        <view class="stats-row">
          <view class="stat-item">
            <text class="stat-num">{{ stats.totalUsers }}</text>
            <text class="stat-label">累计受邀</text>
          </view>
          <view class="stat-divider" />
          <view class="stat-item">
            <text class="stat-num stat-num--green">{{ stats.loginCount }}</text>
            <text class="stat-label">累计登录次数</text>
          </view>
          <view class="stat-divider" />
          <view class="stat-item">
            <text class="stat-num stat-num--blue">{{ stats.videoCount }}</text>
            <text class="stat-label">累计看视频</text>
          </view>
        </view>
        <view class="stats-row">
          <view class="stat-item">
            <text class="stat-num stat-num--blue">{{ (stats.videoCount * 0.01).toFixed(2) }}</text>
            <text class="stat-label">预估收益</text>
          </view>
        </view>
      </template>
    </view>
  </view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { api } from '../../utils/api'
import { useNavBar } from '../../composables/useNavBar'
import { getUserInfo } from '../../utils/auth'
import PunPageNavBar from '../../components/PunPageNavBar.vue'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const QR_STORAGE_KEY = 'streamer_qr_base64'

const qrBase64 = ref('')
const qrTempPath = ref('') // 本地临时文件路径，用于长按保存
const qrLoading = ref(false)
const channel = ref('')
const userId = ref('')

// base64 → 临时文件，供长按保存使用
function writeQrTempFile(base64) {
  // #ifdef MP-WEIXIN
  try {
    const fs = wx.getFileSystemManager()
    const filePath = `${wx.env.USER_DATA_PATH}/streamer_qr_preview.png`
    fs.writeFileSync(filePath, base64, 'base64')
    qrTempPath.value = filePath
  } catch (e) {
    qrTempPath.value = ''
  }
  // #endif
}

// 从本地缓存读取 userId
function loadUserId() {
  const info = getUserInfo()
  userId.value = info?.user_id ? String(info.user_id) : ''
}

// 从 storage 恢复二维码
function restoreQrFromStorage() {
  try {
    const saved = uni.getStorageSync(QR_STORAGE_KEY)
    if (saved) {
      qrBase64.value = saved
      writeQrTempFile(saved)
    }
  } catch (e) {}
}

    const stats = ref({ totalUsers: 0, loginCount: 0, videoCount: 0 })
const statsLoading = ref(false)

async function loadQrCode() {
  if (qrLoading.value) return
  qrLoading.value = true
  try {
    const data = await api.getStreamerQrCode()
    qrBase64.value = data.qrBase64 || ''
    channel.value = data.channel || ''
    if (qrBase64.value) {
      try { uni.setStorageSync(QR_STORAGE_KEY, qrBase64.value) } catch (e) {}
      writeQrTempFile(qrBase64.value)
    }
  } catch (e) {
    uni.showToast({ title: e?.message || '生成失败', icon: 'none' })
  } finally {
    qrLoading.value = false
  }
}

async function loadStats() {
  statsLoading.value = true
  try {
    const data = await api.getStreamerStats()
    stats.value = data
    if (!channel.value && data.channel) channel.value = data.channel
  } catch (e) {
    uni.showToast({ title: '数据加载失败', icon: 'none' })
  } finally {
    statsLoading.value = false
  }
}

function saveQrCode() {
  if (!qrBase64.value) return
  // #ifdef MP-WEIXIN
  const doSave = () => {
    const fs = wx.getFileSystemManager()
    const filePath = `${wx.env.USER_DATA_PATH}/streamer_qr_${Date.now()}.png`
    fs.writeFile({
      filePath,
      data: qrBase64.value,
      encoding: 'base64',
      success: () => {
        wx.saveImageToPhotosAlbum({
          filePath,
          success: () => uni.showToast({ title: '已保存到相册', icon: 'success' }),
          fail: () => {
            wx.showModal({
              title: '提示',
              content: '请长按二维码保存，或者在设置中开启相册权限后重试',
              confirmText: '去设置',
              success: (res) => {
                if (res.confirm) wx.openSetting()
              },
            })
          },
        })
      },
      fail: () => uni.showToast({ title: '文件写入失败', icon: 'none' }),
    })
  }

  const albumDeniedModal = () => {
    wx.showModal({
      title: '提示',
      content: '请长按二维码保存，或在设置中开启「保存到相册」权限后重试',
      confirmText: '去设置',
      success: (r) => {
        if (!r.confirm) return
        wx.openSetting({
          success: (openRes) => {
            if (openRes.authSetting['scope.writePhotosAlbum']) doSave()
          },
        })
      },
    })
  }

  wx.getSetting({
    success: (res) => {
      const album = res.authSetting['scope.writePhotosAlbum']
      if (album === true) {
        doSave()
        return
      }
      // 用户曾拒绝：authorize 不会再弹系统授权框，只能去设置里打开
      if (album === false) {
        albumDeniedModal()
        return
      }
      wx.authorize({
        scope: 'scope.writePhotosAlbum',
        success: doSave,
        fail: albumDeniedModal,
      })
    },
    fail: doSave, // getSetting 失败时直接尝试保存
  })
  // #endif
  // #ifndef MP-WEIXIN
  uni.showToast({ title: '请截图保存', icon: 'none' })
  // #endif
}

function formatTime(str) {
  if (!str) return ''
  return str.slice(0, 16).replace('T', ' ')
}

function back() {
  uni.navigateBack({ delta: 1, fail: () => uni.reLaunch({ url: '/pages/index/index' }) })
}

onShow(() => {
  loadUserId()
  restoreQrFromStorage()
  loadStats()
})
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

.page {
  min-height: 100vh;
  padding: 0 32rpx 60rpx;
  box-sizing: border-box;
  @include pt-page-background;
}

.qr-card,
.stats-card {
  position: relative;
  z-index: 2;
  background: rgba(255, 255, 255, 0.92);
  border-radius: 28rpx;
  padding: 36rpx 32rpx;
  margin-bottom: 28rpx;
  box-shadow: 0 6rpx 20rpx rgba(169, 201, 238, 0.18);
  border: 2rpx solid rgba(169, 201, 238, 0.4);
}

.qr-title {
  font-size: 32rpx;
  font-weight: 800;
  color: #5a6d7a;
  display: block;
  margin-bottom: 8rpx;
}
.qr-desc {
  font-size: 24rpx;
  color: #8eadcf;
  display: block;
  margin-bottom: 28rpx;
}
.qr-wrap {
  display: flex;
  justify-content: center;
  margin-bottom: 24rpx;
}
.qr-img {
  width: 320rpx;
  height: 320rpx;
  border-radius: 16rpx;
  border: 4rpx solid rgba(169, 201, 238, 0.4);
}
.qr-placeholder {
  width: 320rpx;
  height: 320rpx;
  border-radius: 16rpx;
  background: rgba(234, 246, 249, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
}
.qr-placeholder--error {
  background: rgba(255, 230, 230, 0.6);
}
.qr-loading-text {
  font-size: 26rpx;
  color: #8eadcf;
}
.qr-channel-tag {
  display: flex;
  align-items: center;
  gap: 12rpx;
  margin-bottom: 24rpx;
  background: rgba(234, 246, 249, 0.8);
  border-radius: 12rpx;
  padding: 12rpx 18rpx;
}
.qr-channel-label {
  font-size: 22rpx;
  color: #8eadcf;
}
.qr-channel-value {
  font-size: 22rpx;
  font-weight: 700;
  color: #5a6d7a;
  word-break: break-all;
}
.qr-actions {
  display: flex;
  gap: 16rpx;
}
.qr-btn {
  flex: 1;
  background: linear-gradient(135deg, #a3dd9c, #6fb868);
  color: #fff;
  font-size: 28rpx;
  font-weight: 700;
  border-radius: 48rpx;
  border: none;
  padding: 20rpx 0;
  box-shadow: 0 4rpx 14rpx rgba(111, 184, 104, 0.3);
}
.qr-btn--save {
  background: linear-gradient(135deg, #90caf9, #42a5f5);
  box-shadow: 0 4rpx 14rpx rgba(66, 165, 245, 0.3);
}

/* 统计 */
.stats-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24rpx;
}
.stats-title {
  font-size: 32rpx;
  font-weight: 800;
  color: #5a6d7a;
}
.stats-refresh {
  font-size: 24rpx;
  color: #8eadcf;
  padding: 6rpx 18rpx;
  background: rgba(234, 246, 249, 0.9);
  border-radius: 20rpx;
}
.stats-loading {
  text-align: center;
  padding: 40rpx 0;
  color: #8eadcf;
  font-size: 26rpx;
}
.stats-row {
  display: flex;
  align-items: center;
  justify-content: space-around;
  margin-bottom: 28rpx;
  background: rgba(234, 246, 249, 0.6);
  border-radius: 18rpx;
  padding: 24rpx 0;
}
.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8rpx;
}
.stat-num {
  font-size: 48rpx;
  font-weight: 900;
  color: #5a6d7a;
  line-height: 1;
}
.stat-num--green { color: #6fb868; }
.stat-num--blue  { color: #42a5f5; }
.stat-label {
  font-size: 22rpx;
  color: #8eadcf;
}
.stat-divider {
  width: 2rpx;
  height: 60rpx;
  background: rgba(169, 201, 238, 0.4);
}

.user-list-title {
  font-size: 26rpx;
  font-weight: 700;
  color: #8eadcf;
  display: block;
  margin-bottom: 16rpx;
}
.user-item {
  display: flex;
  align-items: center;
  gap: 18rpx;
  padding: 18rpx 0;
  border-bottom: 1rpx solid rgba(169, 201, 238, 0.2);
}
.user-item:last-child { border-bottom: none; }
.user-avatar {
  width: 72rpx;
  height: 72rpx;
  border-radius: 50%;
  background: rgba(234, 246, 249, 0.95);
  border: 2rpx solid rgba(169, 201, 238, 0.4);
  flex-shrink: 0;
}
.user-avatar--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 34rpx;
}
.user-info { flex: 1; }
.user-name {
  font-size: 28rpx;
  font-weight: 600;
  color: #5a6d7a;
  display: block;
}
.user-time {
  font-size: 22rpx;
  color: #8eadcf;
  display: block;
  margin-top: 4rpx;
}
.user-list-empty {
  text-align: center;
  padding: 40rpx 0;
  font-size: 26rpx;
  color: #8eadcf;
}
</style>
