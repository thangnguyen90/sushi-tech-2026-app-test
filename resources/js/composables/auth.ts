import { useMutation, useQuery } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/AuthStore'
import Auth from '@/services/app/Auth'
import { ApiResponse } from '@/shared/interfaces/response'
import { UserPolicyInfo } from '@/shared/interfaces/auth'

export function useUserPolicyStatus() {
    const useAuth = useAuthStore()

    return useQuery({
        queryKey: ['user-policy-status'],
        queryFn: async (): Promise<ApiResponse<UserPolicyInfo>> => {
            const { data } = await Auth.getUserPolicyStatus()
            useAuth.setUser(data.result)
            return data
        },
        refetchOnWindowFocus: false,
        retry: 0,
    })
}

export function useAgreePolicyMutation() {
    return useMutation({
        mutationFn: () => Auth.updateUserPolicy(),
    })
}
