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
      title="意见反馈"
      @left-click="back"
    />

    <view class="form-wrap">
      <view class="form-item">
        <text class="label">反馈类型</text>
        <picker
          :value="typeIndex"
          :range="typeOptions"
          range-key="label"
          @change="onTypeChange"
        >
          <view class="picker-value">
            {{ typeOptions[typeIndex].label }}
          </view>
        </picker>
      </view>

      <view class="form-item">
        <text class="label">反馈内容 <text class="required">*</text></text>
        <textarea
          v-model="content"
          class="textarea"
          placeholder="请描述您的问题或建议（必填）"
          maxlength="500"
          :show-confirm-bar="false"
          :focus="contentFocus"
        />
        <text class="counter">{{ content.length }}/500</text>
      </view>

      <!-- <view class="form-item">
        <text class="label">联系方式（选填）</text>
        <input
          v-model="contact"
          class="input"
          type="text"
          placeholder="方便我们回复您"
        />
      </view> -->

      <view class="btn-wrap">
        <view
          :class="['btn-submit', { disabled: submitting || !content.trim() }]"
          @click="submit"
        >
          <text class="btn-text">{{ submitting ? '提交中…' : '提交反馈' }}</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { api } from '../../utils/api'
import { useNavBar } from '../../composables/useNavBar'
import PunPageNavBar from '../../components/PunPageNavBar.vue'
import { useWechatPageShare } from '../../composables/useWechatPageShare'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

const shareRewardQuotaRef = ref(0)
// #ifdef MP-WEIXIN
useWechatPageShare('意见反馈 · 谐音梗图', shareRewardQuotaRef)
// #endif

const typeOptions = [
  { value: '', label: '请选择' },
  { value: 'bug', label: '题目/答案错误' },
  { value: 'suggest', label: '功能建议' },
  { value: 'other', label: '其他' },
]
const typeIndex = ref(0)
const content = ref('')
const contact = ref('')
const submitting = ref(false)
const contentFocus = ref(false)

function safeDecode(value) {
  if (typeof value !== 'string') return ''
  try {
    return decodeURIComponent(value)
  } catch (_) {
    return value
  }
}

function findTypeIndexByValue(val) {
  const idx = typeOptions.findIndex((item) => item.value === val)
  return idx >= 0 ? idx : 0
}

onLoad((opts = {}) => {
  const type = safeDecode(opts.type).trim()
  if (type) {
    typeIndex.value = findTypeIndexByValue(type)
  }

  const presetContent = safeDecode(opts.content)
  if (presetContent) {
    content.value = presetContent
  }

  if (String(opts.contentFocus || '') === '1') {
    contentFocus.value = true
  }
})

function onTypeChange(e) {
  const i = Number(e.detail.value)
  if (!Number.isNaN(i) && i >= 0 && i < typeOptions.length) {
    typeIndex.value = i
  }
}

function back() {
  uni.navigateBack({ fail: () => { uni.reLaunch({ url: '/pages/levels/levels' }) } })
}

function submit() {
  const text = content.value ? content.value.trim() : ''
  if (!text) {
    uni.showToast({ title: '请填写反馈内容', icon: 'none' })
    return
  }
  if (submitting.value) return
  submitting.value = true
  const type = typeOptions[typeIndex.value].value || undefined
  api.submitFeedback({
    type,
    content: text,
    contact: contact.value ? contact.value.trim() : undefined,
  })
    .then(() => {
      uni.showToast({ title: '感谢反馈，我们会尽快处理', icon: 'success' })
      content.value = ''
      contact.value = ''
      typeIndex.value = 0
      setTimeout(() => back(), 1500)
    })
    .catch((err) => {
      uni.showToast({ title: err.message || '提交失败，请重试', icon: 'none' })
    })
    .finally(() => {
      submitting.value = false
    })
}
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

.form-wrap {
  position: relative;
  z-index: 2;
}
.form-item {
  margin-bottom: 36rpx;
}
.label {
  display: block;
  font-size: 28rpx;
  color: #5a6d7a;
  margin-bottom: 14rpx;
  font-weight: 500;
}
.required {
  color: #c04a38;
}
.picker-value,
.input {
  background: rgba(255, 255, 255, 0.92);
  border: 2rpx solid rgba(169, 201, 238, 0.5);
  border-radius: 20rpx;
  padding: 26rpx 28rpx;
  font-size: 30rpx;
  color: #5a6d7a;
  box-shadow: 0 2rpx 12rpx rgba(169, 201, 238, 0.1);
}
.textarea {
  width: 100%;
  min-height: 240rpx;
  padding: 26rpx 28rpx;
  box-sizing: border-box;
  background: rgba(255, 255, 255, 0.92);
  border: 2rpx solid rgba(169, 201, 238, 0.5);
  border-radius: 20rpx;
  font-size: 30rpx;
  color: #5a6d7a;
  box-shadow: 0 2rpx 12rpx rgba(169, 201, 238, 0.1);
}
.counter {
  display: block;
  text-align: right;
  font-size: 24rpx;
  color: #8eadcf;
  margin-top: 10rpx;
}
.btn-wrap {
  margin-top: 56rpx;
}
.btn-submit {
  height: 96rpx;
  line-height: 96rpx;
  text-align: center;
  background: linear-gradient(145deg, #a8e6a2 0%, #91d58b 100%);
  color: #fff;
  border-radius: 28rpx;
  font-size: 34rpx;
  font-weight: 700;
  letter-spacing: 0.06em;
  box-shadow: 0 12rpx 32rpx rgba(111, 184, 104, 0.28), 0 4rpx 0 rgba(111, 184, 104, 0.12);
}
.btn-submit:active {
  transform: scale(0.98);
}
.btn-submit.disabled {
  opacity: 0.5;
}
.btn-text {
  color: #fff;
}
</style>
