# 🎯 CHUYA HANDOFF: Frontend Quality Remediation — Phase 1+2 (P0 Only)
**Mission:** Fix broken functionality + memory leaks  
**Assigned Detective:** Chuya Nakahara  
**Priority:** P0 / CRITICAL  
**Estimated Effort:** 45 minutes  
**Risk Level:** MINIMAL (pure bug fixes, no architectural changes)  
**Active Directory:** `apps/woosoo-nexus/resources/`

---

## 🚨 DO / DON'T (Read This First, Gravity Boy)

### ✅ DO
- Work **ONLY** in `apps/woosoo-nexus/resources/` (js/components, views, css)
- Make **exact** changes specified below (line-for-line diffs provided)
- Test **each fix individually** before proceeding to next
- Run `npm run build` after Vue component changes
- Verify no TypeScript/ESLint errors after each change
- Take screenshots for visual verification (image upload, menu form)

### ❌ DON'T
- Touch **any** files in `apps/tablet-ordering-pwa/` (wrong app)
- Touch **any** backend files (`app/`, `routes/`, `database/`)
- Touch **any** root-level configs (`deployment.config.json`, `vite.config.ts`) unless specified
- Refactor or "improve" code beyond the specified fixes (stick to the script)
- Batch all changes and test once (test incrementally)
- Skip the acceptance tests (they catch regressions)

---

## 📋 TASK LIST (5 Fixes)

### Task 1: Fix AppImageUpload Upload Button (P0-1)
**File:** `resources/js/components/AppImageUpload.vue`  
**Line:** ~48  
**Current Code:**
```vue
<Button class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark bg-woosoo-accent cursor-pointer text-gray-100 w-50" @click="onFileChange">
  <Upload class="w-4 h-4 mr-2" />
  <span>Upload</span>
</Button>
```

**Required Change:**
```vue
<Button class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark bg-woosoo-accent cursor-pointer text-gray-100 w-50" @click="($refs as any).fileInput.click()">
  <Upload class="w-4 h-4 mr-2" />
  <span>Upload</span>
</Button>
```

**Explanation:**  
The button was calling the file change handler directly instead of triggering the hidden file input's click event. Now clicking "Upload" will properly open the file picker.

**Test:**
1. Navigate to a page with AppImageUpload component (likely menu management)
2. Click the "Upload" button
3. ✅ File picker dialog should open
4. Select an image file
5. ✅ Image preview should appear

---

### Task 2: Fix MenuForm v-model Typo (P0-2)
**File:** `resources/js/components/Menus/MenuForm.vue`  
**Line:** ~155  
**Current Code:**
```vue
<Input id="image" type="file" :v-model="form.image" accept="image/*" @change="onFileChange"
  @input="form.image = $event.target.files[0]" />
```

**Required Change:**
```vue
<Input id="image" type="file" v-model="form.image" accept="image/*" @change="onFileChange"
  @input="form.image = $event.target.files[0]" />
```

**Explanation:**  
Removed the colon prefix from `:v-model`. The colon was making Vue treat this as a prop binding instead of the v-model directive.

**Test:**
1. Open MenuForm dialog (edit a menu item)
2. Upload a new image
3. Submit the form
4. ✅ Check Chrome DevTools Network tab → POST request should include the image file in the payload
5. ✅ Verify the menu item's image updates in the database

---

### Task 3: Fix KitchenTicket Note Display (P0-3) — ✅ RESOLVED
**File:** `resources/js/components/KitchenTicket.vue`  
**Lines:** 22-31  

**Investigation Complete:**  
Component receives `:item-data="computedOrder.items"` which is an array of `OrderedMenu[]`. Each item has `notes?: string | null` as a per-item property.

**Root Cause:**  
Code iterated `itemData` as array but then tried to access `itemData.note` (array property) which is always undefined. The component had conflicting type assumptions.

**Resolution Applied (Option A):**
```vue
<!-- BEFORE: Wrong — tried to access .note on array -->
<div v-for="(item, idx) in itemData">
  ({{ item.quantity }}) {{ item.name }}
</div>
<div class="text-xs leading-snug mt-2">
  <p class="font-bold">** ITEM NOTES:</p>
  <p>{{ itemData.note || '(No special requests/notes)' }}</p>  <!-- ❌ -->
</div>

<!-- AFTER: Correct — show notes per-item -->
<div v-for="(item, idx) in itemData" :key="item.id ?? idx" class="mb-1">
  <div>({{ item.quantity }}) {{ item.name }}</div>
  <div v-if="item.notes" class="ml-4 text-[10px] italic">
    Note: {{ item.notes }}
  </div>
</div>
```

**Impact:**  
Order item notes (e.g., "No onions", "Extra spicy") now display correctly on kitchen tickets. Kitchen staff can see special requests.

---

### Task 4: Fix AppHeader Scroll Listener Leak (P0-4)
**File:** `resources/js/components/AppHeader.vue`  
**Lines:** ~65-75  
**Current Code:**
```ts
onMounted(() => {
  window.addEventListener('scroll', handleScroll);
  
  onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
  });
});
```

**Required Change:**
```ts
onMounted(() => {
  window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
});
```

**Explanation:**  
Vue 3 Composition API does **not** execute cleanup functions registered inside lifecycle hook bodies. The `onUnmounted` call must be at the top level of `<script setup>`, not nested inside `onMounted`.

**Test:**
1. Open Chrome DevTools → Performance Monitor
2. Navigate between pages 10 times (e.g., Dashboard → Orders → Dashboard → ...)
3. ✅ Event listener count should remain **stable** (not increase by 1 per navigation)
4. In DevTools Console, run: `getEventListeners(window).scroll.length`
5. ✅ Should return 1 or 0 (not accumulating)

---

### Task 5: Fix MenuForm Blob URL Leak (P0-5)
**File:** `resources/js/components/Menus/MenuForm.vue`  
**Lines:** ~97-105  
**Current Code:**
```ts
onMounted(() => {
  return () => {
    if (previewImage.value && previewImage.value.startsWith('blob:')) {
      URL.revokeObjectURL(previewImage.value);
    }
  };
});
```

**Required Change:**
```ts
onMounted(() => {
  // onMounted setup if needed (currently empty, but keep the hook)
});

onUnmounted(() => {
  if (previewImage.value && previewImage.value.startsWith('blob:')) {
    URL.revokeObjectURL(previewImage.value);
  }
});
```

**Explanation:**  
Same issue as Task 4. Cleanup must be in `onUnmounted`, not returned from `onMounted`.

**Test:**
1. Open Chrome → `chrome://blob-internals/`
2. Open MenuForm dialog → upload an image (creates blob URL)
3. Close the dialog
4. Refresh `chrome://blob-internals/`
5. ✅ The blob URL created in step 2 should be **revoked** (not listed)
6. Repeat 10× → blob count should stay at 0-1 (not accumulate)

**Alternative Test (Memory Profiler):**
1. DevTools → Memory → Take Heap Snapshot
2. Open/close MenuForm 20 times with image uploads
3. Take another Heap Snapshot
4. ✅ Search for "blob" in snapshot → retained size should be < 1MB (no leaks)

---

## 🧪 ACCEPTANCE CRITERIA (Gate 1 — Manual QA)

Run **all** tests below after completing Tasks 1, 2, 4, 5:

### Test Suite 1: AppImageUpload (Task 1)
- [ ] **T1.1:** Click "Upload" button → file picker opens
- [ ] **T1.2:** Select image → preview displays correctly
- [ ] **T1.3:** Click "Upload" again → can change selection
- [ ] **T1.4:** Click upload without selecting → no errors in console

### Test Suite 2: MenuForm (Task 2)
- [ ] **T2.1:** Open menu edit dialog
- [ ] **T2.2:** Upload new image
- [ ] **T2.3:** Submit form → Chrome DevTools Network tab shows image in POST payload
- [ ] **T2.4:** Verify database update → menu item's `img_url` changes
- [ ] **T2.5:** Refresh page → new image displays

### Test Suite 3: AppHeader Memory Leak (Task 4)
- [ ] **T3.1:** Open DevTools Console
- [ ] **T3.2:** Run `getEventListeners(window).scroll.length` → note count
- [ ] **T3.3:** Navigate to different page and back
- [ ] **T3.4:** Run command again → count should NOT increase
- [ ] **T3.5:** Repeat navigation 10× → count remains stable

### Test Suite 4: MenuForm Blob Leak (Task 5)
- [ ] **T4.1:** Navigate to `chrome://blob-internals/`
- [ ] **T4.2:** Open MenuForm, upload image, close dialog
- [ ] **T4.3:** Refresh blob-internals → blob URL revoked (not listed)
- [ ] **T4.4:** Repeat 10× → no accumulation

### Test Suite 5: Build Validation
- [ ] **T5.1:** Run `npm run build` → no TypeScript errors
- [ ] **T5.2:** Run `npm run type-check` (if available) → passes
- [ ] **T5.3:** Check browser console → no Vue warnings
- [ ] **T5.4:** No ESLint errors in changed files

---

## 🎯 DELIVERABLES

### 1. Code Changes
Provide a summary of all edits:
```
✅ AppImageUpload.vue:48 — Changed @click to trigger file input
✅ MenuForm.vue:155 — Fixed :v-model → v-model
⏸️ KitchenTicket.vue — Awaiting data structure clarification
✅ AppHeader.vue:70 — Moved onUnmounted outside onMounted
✅ MenuForm.vue:97 — Moved blob cleanup to onUnmounted
```

### 2. Test Results
For each test suite, report:
- ✅ PASS / ❌ FAIL
- If FAIL, provide error message or screenshot

### 3. Build Output
Attach the last 20 lines of `npm run build` output.

### 4. Screenshots
- Screenshot of file picker opening (T1.1)
- Screenshot of Chrome DevTools Network tab showing image upload (T2.3)
- Screenshot of `getEventListeners(window).scroll` output before/after navigation (T3.4)

---

## 🚨 FAILURE MODES & MANUAL SIMULATION

### FM-1: File Picker Doesn't Open
**Symptom:** Clicking Upload does nothing  
**Root Cause:** `$refs.fileInput` is undefined or ref name mismatch  
**Debug:**
1. Add `console.log($refs)` in the @click handler
2. Verify ref name is exactly `fileInput` (case-sensitive)
3. Ensure `ref="fileInput"` exists on `<input type="file">`

**Recovery:**
If `($refs as any).fileInput` doesn't work, try:
```vue
<script setup>
import { ref } from 'vue';
const fileInput = ref<HTMLInputElement>();
</script>

<input ref="fileInput" ... />
<Button @click="fileInput?.click()">
```

### FM-2: v-model Still Not Working
**Symptom:** Form submits but image is null  
**Root Cause:** Multiple bindings conflict (@input handler overrides v-model)  
**Debug:**
1. Check if `@input="form.image = $event.target.files[0]"` is still present
2. This **duplicates** v-model's job

**Recovery:**
Remove the `@input` handler entirely if v-model is present:
```vue
<Input v-model="form.image" @change="onFileChange" />
<!-- Remove @input handler -->
```

### FM-3: Scroll Listener Still Leaks
**Symptom:** Event count increases per navigation  
**Root Cause:** `handleScroll` function is redeclared on each component mount (new reference)  
**Debug:**
```ts
const handleScroll = () => { ... };  // Must be declared ONCE at top level
```

**Recovery:**
Ensure `handleScroll` is declared **outside** any lifecycle hooks, so the function reference is stable.

### FM-4: Blob URLs Still Leak
**Symptom:** Blob count grows in chrome://blob-internals  
**Root Cause:** `previewImage.value` is reset before `onUnmounted` reads it  
**Debug:**
1. Add `console.log('Revoking:', previewImage.value)` in onUnmounted
2. If logs "Revoking: null", the value was cleared too early

**Recovery:**
Store blob URL in a separate variable before clearing:
```ts
let blobUrl: string | null = null;

// When creating blob:
blobUrl = URL.createObjectURL(file);
previewImage.value = blobUrl;

// On unmount:
if (blobUrl?.startsWith('blob:')) {
  URL.revokeObjectURL(blobUrl);
}
```

---

## 📞 ESCALATION PROTOCOL

**Escalate to Ranpo if:**
1. Task 3 (KitchenTicket) data structure cannot be determined from codebase
2. Any test fails after 2 fix attempts
3. TypeScript errors appear that require type definition changes
4. You discover additional related bugs during testing

**Required Info for Escalation:**
- Task number
- Error message (full stack trace)
- Code you tried
- What you expected vs what happened

---

## 🎤 SIGN-OFF

Once all tasks complete:

1. **Commit changes:**
   ```powershell
   git add resources/js/components/AppImageUpload.vue
   git add resources/js/components/Menus/MenuForm.vue
   git add resources/js/components/AppHeader.vue
   # Add others as needed
   git commit -m "fix(frontend): P0 bugs - upload button, v-model typo, memory leaks

   - AppImageUpload: fix upload button to trigger file input click
   - MenuForm: remove colon from :v-model directive
   - AppHeader: fix scroll listener leak (move onUnmounted outside onMounted)
   - MenuForm: fix blob URL memory leak

   Resolves woosoo-nexus CASE_FILE_FRONTEND_QUALITY_AUDIT P0-1, P0-2, P0-4, P0-5"
   ```

2. **Report to Ranpo:**
   ```
   Mission Phase 1+2 Complete.
   
   Results:
   - 4 of 5 tasks completed (Task 3 pending data structure clarification)
   - All acceptance tests PASS
   - Build successful, no errors
   - Screenshots attached
   
   Ready for Phase 3 (Accessibility) or Phase 4 (Dark Mode/Performance) deployment.
   ```

---

**This case will remain open until you report back, Chuya. Don't keep me waiting.**

— Ranpo Edogawa  
April 13, 2026

