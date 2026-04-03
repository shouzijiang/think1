<template>
  <view class="page">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
      <view class="bg-glow" />
    </view>

    <view :style="{ height: statusBarHeight + 'px', width: '100%' }"></view>

    <UserProfileNav ref="userProfileRef" />

    <view class="section">
      <text class="section-title">常用入口</text>
      <view class="cell" @click="goFeedback">
        <text class="cell-icon">📝</text>
        <text class="cell-text">意见反馈</text>
        <text class="cell-arrow">›</text>
      </view>
      <view class="cell" @click="onCocreate">
        <text class="cell-icon">💡</text>
        <text class="cell-text">共创关卡</text>
        <text class="cell-arrow">›</text>
      </view>
    </view>

    <text class="hint">头像与昵称与首页顶部一致，修改后会同步保存。</text>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { wechatLogin } from '../../utils/auth'
import UserProfileNav from '../../components/UserProfileNav.vue'
import { useNavBar } from '../../composables/useNavBar'
import { useWechatPageShare } from '../../composables/useWechatPageShare'

const { statusBarHeight } = useNavBar()
const userProfileRef = ref(null)

// #ifdef MP-WEIXIN
useWechatPageShare('我的 · 谐音梗图')
// #endif

onShow(async () => {
  // #ifdef MP-WEIXIN
  try {
    await wechatLogin()
  } catch (e) {
    console.warn('wechatLogin 失败', e)
  }
  // #endif
  userProfileRef.value?.loadUserInfo?.()
})

function goFeedback() {
  uni.navigateTo({ url: '/pages/feedback/feedback' })
}

function onCocreate() {
  uni.showToast({ title: '敬请期待~', icon: 'none' })
}
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

.page {
  min-height: 100vh;
  position: relative;
  padding: 0 40rpx 48rpx;
  padding-bottom: calc(48rpx + env(safe-area-inset-bottom));
  box-sizing: border-box;
  @include pt-page-background;
}

.section {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 560rpx;
  margin: 0 auto;
}

.section-title {
  display: block;
  font-size: 26rpx;
  font-weight: 700;
  color: $pt-muted;
  margin-bottom: 20rpx;
  letter-spacing: 0.06em;
}

.cell {
  display: flex;
  align-items: center;
  gap: 20rpx;
  padding: 28rpx 24rpx;
  margin-bottom: 20rpx;
  background: rgba(255, 255, 255, 0.78);
  border-radius: 24rpx;
  border: 2rpx solid $pt-border;
  box-shadow: 0 6rpx 18rpx $pt-shadow-soft;
}
.cell:active {
  opacity: 0.92;
  transform: scale(0.99);
}

.cell-icon {
  font-size: 40rpx;
  line-height: 1;
}

.cell-text {
  flex: 1;
  font-size: 28rpx;
  font-weight: 700;
  color: $pt-text;
}

.cell-arrow {
  font-size: 36rpx;
  color: $pt-muted;
  font-weight: 300;
}

.hint {
  position: relative;
  z-index: 2;
  display: block;
  margin-top: 32rpx;
  text-align: center;
  font-size: 22rpx;
  color: $pt-muted;
  line-height: 1.5;
  padding: 0 20rpx;
}
</style>
