<template>
    <div>
        <!-- logo -->
        <RouterLink :to="{ name: 'users' }">
            <div class="text-3xl font-nabla hidden md:block">
                <span class="logo-char animation-delay-100">S</span>
                <span class="logo-char animation-delay-200">P</span>
                <span class="logo-char animation-delay-300">E</span>
                <span class="logo-char animation-delay-400">C</span>
                <span class="logo-char animation-delay-500">T</span>
                <span class="logo-char animation-delay-600">R</span>
                <span class="logo-char animation-delay-700">U</span>
                <span class="logo-char animation-delay-800">M</span>
                <span class="">{{ ' ' }}</span>
                <span class="logo-char animation-delay-900">a</span>
                <span class="logo-char animation-delay-1000">d</span>
                <span class="logo-char animation-delay-1100">m</span>
                <span class="logo-char animation-delay-1200">i</span>
                <span class="logo-char animation-delay-1300">n</span>

            </div>
            <div class="text-2xl font-nabla md:hidden">
                <span class="logo-char animation-delay-100">R</span>
                <span class="">{{ ' ' }}</span>
                <span class="logo-char animation-delay-200">P</span>
            </div>
        </RouterLink>

        <div class="flex-start gap-4 lg:gap-8">
            <div class="flex flex-col gap-0.5">
                <div class="flex-start gap-6 lg:gap-8">
                    <RouterLink :to="{ name: 'users' }">
                        <template v-slot="{ isActive }">
                            <span
                                class="lg:text-lg font-bold"
                                :class="[
                                    isActive
                                        ? 'text-active'
                                        : 'hover:text-active-hover',
                                ]"
                            >Users</span
                            >
                        </template>
                    </RouterLink>

                    <RouterLink :to="{ name: 'roles' }">
                        <template v-slot="{ isActive }">
                            <span
                                class="lg:text-lg font-bold"
                                :class="[
                                    isActive
                                        ? 'text-active'
                                        : 'hover:text-active-hover',
                                ]"
                            >Roles</span
                            >
                        </template>
                    </RouterLink>

                    <RouterLink :to="{ name: 'permissions' }">
                        <template v-slot="{ isActive }">
                            <span
                                class="lg:text-lg font-bold"
                                :class="[
                                    isActive
                                        ? 'text-active'
                                        : 'hover:text-active-hover',
                                ]"
                            >Permissions</span
                            >
                        </template>
                    </RouterLink>

                    <span
                        class="lg:text-lg font-bold hover:text-active-hover cursor-pointer text-red-200"
                        @click="onLogout"
                    >
                        Logout
                    </span>
                </div>

                <div
                    v-if="userStore.user?.id"
                    class="text-xs text-emerald-300 flex justify-end"
                >
                    {{ `${userStore.user?.name} (${userStore.user?.email})` }}
                </div>
            </div>
        </div>
    </div>

</template>
<script setup>
    import useHttpRequest from '../composables/useHttpRequest';
    import useUserStore from '../store/useUserStore';
    import useRoleStore from '../store/useRoleStore';
    import usePermissionStore from '../store/usePermissionStore';
    import useAppRouter from '../composables/useAppRouter';


    const { index: logout } = useHttpRequest('/logout');
    const { pushToRoute } = useAppRouter();

    const userStore = useUserStore();
    const roleStore = useRoleStore();
    const permissionStore = usePermissionStore();

    const onLogout = async () => {
        const isLoggedOut = await logout();
        if (isLoggedOut) {
            userStore.setUser(null);
            userStore.users = [];
            roleStore.roles = [];
            permissionStore.permissions = [];

            await pushToRoute({ name: 'login' });
        }
    };
</script>



