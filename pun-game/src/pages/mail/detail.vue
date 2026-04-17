<template>
  <view class="page">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
      <view class="bg-glow" />
    </view>

    <PunPageNavBar
      :status-bar-height="statusBarHeight"
      :nav-bar-height="navBarHeight"
      :menu-button-height="menuButtonHeight"
      title="邮件详情"
      @left-click="back"
    />

    <view class="wrap">
      <view v-if="loading" class="hint">加载中…</view>

      <view v-else-if="!mail" class="empty">
        <text class="empty-text">邮件不存在</text>
      </view>

      <view v-else class="card">
        <text class="title">{{ mail.title }}</text>
        <text v-if="mail.createdAt" class="time">{{ mail.createdAt }}</text>
        <view class="content">{{ mail.content }}</view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import PunPageNavBar from '../../components/PunPageNavBar.vue'
import { useNavBar } from '../../composables/useNavBar'
import { api } from '../../utils/api'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const loading = ref(false)
const mail = ref(null)

function back() {
  uni.navigateBack({
    fail: () => uni.reLaunch({ url: '/pages/index/index' }),
  })
}

async function loadDetail(id) {
  if (!id) return
  loading.value = true
  try {
    const data = await api.getMailDetail({ id })
    mail.value = data || null
  } catch (e) {
    uni.showToast({ title: e?.message || '加载失败', icon: 'none' })
  } finally {
    loading.value = false
  }
}

onLoad((opts = {}) => {
  const id = Number(opts.id)
  loadDetail(Number.isFinite(id) ? id : 0)
})
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

.page {
  min-height: 100vh;
  position: relative;
  padding-bottom: 80rpx;
  padding-left: 40rpx;
  padding-right: 40rpx;
  box-sizing: border-box;
  @include pt-page-background;
}

.wrap {
  position: relative;
  z-index: 2;
  margin-top: 28rpx;
}

.hint {
  text-align: center;
  color: $pt-muted;
  font-size: 26rpx;
  font-weight: 600;
}

.empty {
  margin-top: 120rpx;
  display: flex;
  justify-content: center;
}

.empty-text {
  color: $pt-muted;
  font-size: 28rpx;
  font-weight: 700;
}

.card {
  background: rgba(255, 255, 255, 0.9);
  border: 2rpx solid rgba(169, 201, 238, 0.35);
  border-radius: 28rpx;
  padding: 28rpx 24rpx;
  box-shadow: 0 8rpx 24rpx rgba(169, 201, 238, 0.12);
}

.title {
  display: block;
  font-size: 32rpx;
  font-weight: 900;
  color: $pt-text;
  line-height: 1.35;
  margin-bottom: 14rpx;
}

.time {
  display: block;
  font-size: 22rpx;
  font-weight: 600;
  color: $pt-muted;
  margin-bottom: 18rpx;
}

.content {
  font-size: 26rpx;
  font-weight: 600;
  color: #6a7f8c;
  white-space: pre-wrap;
  word-break: break-word;
  line-height: 1.6;
}
</style>

