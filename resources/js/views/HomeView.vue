<template>
    <h4>SUSHI TECH</h4>
    <ContractMatchingModal
        v-model="contractModal"
        :description="$t('contract.description')"
        @update:model-value="(v) => (contractModal = v)"
        @update:confirm="confirmPolicy"
    />
</template>
<script setup lang="ts">
import { useAgreePolicyMutation, useUserPolicyStatus } from "@/composables/auth";
import { useAuthStore } from "@/stores/AuthStore";
import { defineAsyncComponent, ref, watch } from "vue";

const storeAuth = useAuthStore();
const contractModal = ref<boolean>(false);
const ContractMatchingModal = defineAsyncComponent(() => import("@/components/modals/ContractMatchingModal.vue"));

const { mutate } = useAgreePolicyMutation();
useUserPolicyStatus();

const confirmPolicy = () => {
    mutate(undefined, {
        onSuccess: () => {
            contractModal.value = false;
        },
        onError: (error) => {
            console.error(error);
            contractModal.value = false;
        }
    });
};

watch(
    () => storeAuth.user,
    (user) => {
        if (user) contractModal.value = !user.policy_agreed;
    },
    { immediate: true }
);
</script>
