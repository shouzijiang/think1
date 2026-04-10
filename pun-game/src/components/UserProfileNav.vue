<template>
  <view class="profile-row">
    <!-- #ifdef MP-WEIXIN -->
    <button
      class="avatar-btn avatar-btn--card"
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
    <!-- #endif -->
    <!-- #ifndef MP-WEIXIN -->
    <view class="avatar-fallback" :style="navBtnSizeStyle">
      <image
        v-if="userInfo?.avatar"
        class="user-avatar"
        :src="userInfo.avatar"
        mode="aspectFill"
      />
      <view v-else class="user-avatar user-avatar-placeholder">👤</view>
    </view>
    <view class="nickname-wrapper">
      <text class="user-nickname">{{ userInfo?.nickname || '用户' }}</text>
      <text class="edit-hint">昵称与头像仅微信端可编辑</text>
    </view>
    <!-- #endif -->
  </view>
</template>

<script setup>
import { useUserProfileNav } from '../composables/useUserProfileNav'

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
.profile-row {
  display: flex;
  align-items: center;
  gap: 20rpx;
  width: 100%;
  box-sizing: border-box;
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
.avatar-btn--card {
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
.avatar-btn--card .user-avatar,
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
  font-size: 30rpx;
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
  font-size: 22rpx;
  color: #8eadcf;
  font-weight: 400;
  display: inline-block;
  align-self: flex-start;
}
.user-nickname {
  flex: 1;
  font-size: 30rpx;
  font-weight: 700;
  color: #5a6d7a;
}
</style>
