// resources/js/composables/useUpdateModel.ts
import { ref, Ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
// import { route } from 'ziggy-js';

interface UpdateModelOptions {
  method?: 'put' | 'post';
  onSuccessCallback?: (page: any) => void;
  onErrorCallback?: (errors: any) => void;
  preserveState?: boolean;
  preserveScroll?: boolean;
}

export function useUpdateModel<T>(
  modelName: string,
  routeName: string,
  itemsRef: Ref<T[]>
) {
  const editingItemId = ref<number | null>(null);
  const form = useForm<{ image?: File | null; [key: string]: any }>({});

  function startEditing(item: T, fields: { [key: string]: any } = {}) {
    editingItemId.value = (item as any).id;
    form.reset();
    Object.keys(fields).length ? Object.assign(form, fields) : Object.assign(form, item);
  }

  function cancelEditing() {
    editingItemId.value = null;
    form.reset();
  }

  function updateItem(itemId: number, options: UpdateModelOptions = {}) {
    const {
      method = 'put',
      onSuccessCallback,
      onErrorCallback,
      preserveState = true,
      preserveScroll = true,
    } = options;

    form[method](route(`${routeName}.${method === 'post' ? 'upload.image' : 'update'}`, itemId), {
      preserveState,
      preserveScroll,
      onSuccess: (page: any) => {
        if (page.props[modelName]) {
          const updatedItem = page.props[modelName];
          const index = itemsRef.value.findIndex((item: any) => item.id === updatedItem.id);
          if (index !== -1) {
            itemsRef.value[index] = { ...updatedItem };
          }
        }
        editingItemId.value = null;
        form.reset();
        if (onSuccessCallback) onSuccessCallback(page);
      },
      onError: (errors: any) => {
        console.error('Update errors:', errors);
        if (onErrorCallback) onErrorCallback(errors);
      },
      onFinish: () => {
        // if (!page.props[modelName]) {
          router.reload({ only: [modelName] });
        // }
      },
    });
  }

  return {
    editingItemId,
    form,
    startEditing,
    cancelEditing,
    updateItem,
  };
}