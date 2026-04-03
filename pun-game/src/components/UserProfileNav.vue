<template>
  <!-- 与首页一致：nav-bar 高度 = 胶囊区；头像区尺寸 = nav-btn（menuButtonHeight） -->
  <view class="nav-bar" :style="{ height: navBarHeight + 'px' }">
    <view class="user-header">
      <!-- #ifdef MP-WEIXIN -->
      <view class="user-info">
        <view class="avatar-wrapper">
          <button
            class="avatar-btn avatar-btn--nav"
            open-type="chooseAvatar"
            :style="navBtnSizeStyle"
            @chooseavatar="onChooseAvatar"
          >
            <image
              v-if="userInfo?.avatar"
              class="user-avatar"
              :src="userInfo.avatar"
              mode="aspectFill"
            />
            <view v-else class="user-avatar user-avatar-placeholder">👤</view>
          </button>
        </view>
        <view class="nickname-wrapper">
          <input
            type="nickname"
            class="nickname-input"
            placeholder="点击设置昵称"
            :value="userInfo?.nickname || ''"
            @blur="handleNicknameBlur"
            @confirm="handleNicknameConfirm"
          />
          <text class="edit-hint">点击昵称/头像即可修改</text>
        </view>
      </view>
      <!-- #endif -->
      <!-- #ifndef MP-WEIXIN -->
      <view class="user-info user-info-readonly">
        <view class="avatar-wrapper">
          <view class="avatar-fallback" :style="navBtnSizeStyle">
            <image
              v-if="userInfo?.avatar"
              class="user-avatar"
              :src="userInfo.avatar"
              mode="aspectFill"
            />
            <view v-else class="user-avatar user-avatar-placeholder">👤</view>
          </view>
        </view>
        <view class="nickname-wrapper">
          <text class="user-nickname">{{ userInfo?.nickname || '用户' }}</text>
        </view>
      </view>
      <!-- #endif -->
    </view>
  </view>
</template>

<script setup>
import { useNavBar } from '../composables/useNavBar'
import { useUserProfileNav } from '../composables/useUserProfileNav'

const { navBarHeight } = useNavBar()
const {
  userInfo,
  navBtnSizeStyle,
  loadUserInfo,
  onChooseAvatar,
  handleNicknameBlur,
  handleNicknameConfirm,
} = useUserProfileNav()

defineExpose({ loadUserInfo })
</script>

<style lang="scss" scoped>
.nav-bar {
  position: relative;
  z-index: 2;
  width: 100%;
  box-sizing: border-box;
  margin-bottom: 24rpx;
}

.user-header {
  position: absolute;
  left: -20rpx;
  top: 60%;
  transform: translateY(-50%);
  z-index: 2;
  display: flex;
  align-items: center;
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
}

.user-info {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 20rpx;
  max-width: 100%;
  padding: 8rpx 20rpx 8rpx 12rpx;
  background: rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-radius: 100rpx;
  box-shadow: 0 6rpx 20rpx rgba(169, 201, 238, 0.22);
  border: 2rpx solid rgba(169, 201, 238, 0.55);
  box-sizing: border-box;
}
.user-info-readonly {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 20rpx;
  max-width: 100%;
  padding: 8rpx 20rpx 8rpx 12rpx;
  border-radius: 100rpx;
  background: rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 2rpx solid rgba(169, 201, 238, 0.55);
  box-shadow: 0 6rpx 20rpx rgba(169, 201, 238, 0.22);
  box-sizing: border-box;
}
.avatar-wrapper {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}
.avatar-btn {
  padding: 0;
  margin: 0;
  background: transparent;
  border: none;
  line-height: 1;
  position: relative;
}
.avatar-btn::after {
  border: none;
}
.avatar-btn--nav {
  flex-shrink: 0;
  box-sizing: border-box;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.92);
  border: 2rpx solid rgba(169, 201, 238, 0.65);
  box-shadow: 0 4rpx 14rpx rgba(169, 201, 238, 0.2);
}
.avatar-fallback {
  flex-shrink: 0;
  box-sizing: border-box;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.92);
  border: 2rpx solid rgba(169, 201, 238, 0.65);
  box-shadow: 0 4rpx 14rpx rgba(169, 201, 238, 0.2);
}
.avatar-btn--nav .user-avatar,
.avatar-fallback .user-avatar {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  border: none;
  display: block;
}
.user-avatar-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  font-size: 48rpx;
  background: rgba(234, 246, 249, 0.95);
  filter: saturate(0.88);
}
.nickname-wrapper {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 4rpx;
}
.nickname-input {
  width: 100%;
  font-size: 28rpx;
  font-weight: 700;
  color: #5a6d7a;
  background: transparent;
  border: none;
  padding: 0;
  margin: 0;
  height: auto;
  line-height: 1.2;
}
.nickname-input::placeholder {
  color: #8eadcf;
  font-weight: 600;
}
.edit-hint {
  font-size: 20rpx;
  color: #8eadcf;
  font-weight: 400;
  border-radius: 12rpx;
  display: inline-block;
  align-self: flex-start;
}
.user-nickname {
  flex: 1;
  font-size: 28rpx;
  font-weight: 700;
  color: #5a6d7a;
}
</style>
