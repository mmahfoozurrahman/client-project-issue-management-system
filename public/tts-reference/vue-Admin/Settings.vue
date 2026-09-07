<template>
    <AdminLayout :pending-count="0">
        <Head title="সিস্টেম সেটিংস" />

        <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-700 mb-1" style="color:#1a2e22;"><i class="bi bi-gear me-2"></i>সিস্টেম সেটিংস</h4>
                <p class="text-muted mb-0" style="font-size:.9rem;">লারাকোডভল্ট প্ল্যাটফর্মের কনফিগারেশন পরিচালনা করুন</p>
            </div>
        </div>

        <div class="row g-4 align-items-start">

            <!-- Sidebar Nav -->
            <div class="col-lg-3">
                <div class="s-nav-card">
                    <button v-for="tab in tabs" :key="tab.id" class="s-nav-btn"
                        :class="{ active: activeTab === tab.id }" @click="activeTab = tab.id">
                        <span class="s-nav-icon"><i :class="tab.icon"></i></span>
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="col-lg-9">

                <!-- General -->
                <div v-show="activeTab === 'general'">
                    <form @submit.prevent="save('general')">
                        <div class="s-card mb-4">
                            <div class="s-card-hd"><h6 class="fw-700 mb-0"><i class="bi bi-house me-2 text-primary"></i>সাইট পরিচিতি</h6></div>
                            <div class="s-card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-600">সাইটের নাম</label>
                                        <input v-model="form.site_name" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-600">ট্যাগলাইন</label>
                                        <input v-model="form.site_tagline" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-600">যোগাযোগ ইমেইল</label>
                                        <input v-model="form.contact_email" type="email" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-600">সাপোর্ট ফোন</label>
                                        <input v-model="form.support_phone" type="text" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-600">সাইটের বিবরণ</label>
                                        <textarea v-model="form.site_description" class="form-control" rows="3"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-600">ফেসবুক গ্রুপ লিংক (Half-yearly subscribers-দের ড্যাশবোর্ডে প্রদর্শনের জন্য)</label>
                                        <input v-model="form.fb_group_link" type="url" class="form-control" placeholder="https://facebook.com/groups/...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="s-card mb-4">
                            <div class="s-card-hd"><h6 class="fw-700 mb-0"><i class="bi bi-toggles me-2 text-primary"></i>সাইটের আচরণ</h6></div>
                            <div class="s-card-body">
                                <template v-for="toggle in generalToggles" :key="toggle.key">
                                    <div class="toggle-row border-bottom">
                                        <div>
                                            <div class="fw-600" style="font-size:.9rem;">{{ toggle.label }}</div>
                                            <small class="text-muted">{{ toggle.desc }}</small>
                                        </div>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" :checked="form[toggle.key] === '1'" @change="form[toggle.key] = $event.target.checked ? '1' : '0'">
                                        </div>
                                    </div>
                                    <div v-if="toggle.key === 'maintenance_mode' && form.maintenance_mode === '1'" class="p-3 my-2 bg-light border rounded-3 animate-fade-in">
                                        <label class="form-label fw-600 text-dark mb-1" style="font-size:.85rem;">মেইনটেন্যান্স / উন্নয়নশীল ব্যানার টেক্সট</label>
                                        <input v-model="form.maintenance_banner_text" type="text" class="form-control" placeholder="ব্যানার টেক্সট লিখুন...">
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary fw-600" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-check2 me-1"></i>পরিবর্তন সংরক্ষণ করুন
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Email -->
                <div v-show="activeTab === 'email'">
                    <form @submit.prevent="save('email')">
                        <div class="s-card mb-4">
                            <div class="s-card-hd"><h6 class="fw-700 mb-0"><i class="bi bi-server me-2 text-primary"></i>SMTP কনফিগারেশন</h6></div>
                            <div class="s-card-body">
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label fw-600">SMTP হোস্ট</label><input v-model="form.smtp_host" type="text" class="form-control" placeholder="smtp.gmail.com"></div>
                                    <div class="col-md-3"><label class="form-label fw-600">পোর্ট</label><input v-model="form.smtp_port" type="number" class="form-control" placeholder="587"></div>
                                    <div class="col-md-3"><label class="form-label fw-600">এনক্রিপশন</label>
                                        <select v-model="form.smtp_encryption" class="form-select"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="">None</option></select>
                                    </div>
                                    <div class="col-md-6"><label class="form-label fw-600">SMTP ইউজারনেম</label><input v-model="form.smtp_username" type="text" class="form-control"></div>
                                    <div class="col-md-6"><label class="form-label fw-600">SMTP পাসওয়ার্ড</label><input v-model="form.smtp_password" type="password" class="form-control" placeholder="••••••••"></div>
                                    <div class="col-md-6"><label class="form-label fw-600">প্রেরকের নাম</label><input v-model="form.mail_from_name" type="text" class="form-control" placeholder="লারাকোডভল্ট"></div>
                                    <div class="col-md-6"><label class="form-label fw-600">প্রেরকের ইমেইল</label><input v-model="form.mail_from_address" type="email" class="form-control"></div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary fw-600" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-check2 me-1"></i>ইমেইল সেটিংস সেভ করুন
                            </button>
                        </div>
                    </form>
                </div>

                <!-- SEO -->
                <div v-show="activeTab === 'seo'">
                    <form @submit.prevent="save('seo')">
                        <div class="s-card mb-4">
                            <div class="s-card-hd"><h6 class="fw-700 mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>মেটা তথ্য</h6></div>
                            <div class="s-card-body">
                                <div class="row g-3">
                                    <div class="col-12"><label class="form-label fw-600">মেটা শিরোনাম</label><input v-model="form.seo_title" type="text" class="form-control" maxlength="60"></div>
                                    <div class="col-12"><label class="form-label fw-600">মেটা বিবরণ</label><textarea v-model="form.seo_description" class="form-control" rows="3" maxlength="160"></textarea></div>
                                    <div class="col-12"><label class="form-label fw-600">কীওয়ার্ড</label><input v-model="form.seo_keywords" type="text" class="form-control" placeholder="কমা দিয়ে আলাদা করুন"></div>
                                    <div class="col-12"><label class="form-label fw-600">Google Analytics ID</label><input v-model="form.google_analytics_id" type="text" class="form-control" placeholder="G-XXXXXXXXXX"></div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary fw-600" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-check2 me-1"></i>SEO সেটিংস সেভ করুন
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security -->
                <div v-show="activeTab === 'security'">
                    <form @submit.prevent="save('security')">
                        <div class="s-card mb-4">
                            <div class="s-card-hd"><h6 class="fw-700 mb-0"><i class="bi bi-shield-lock me-2 text-primary"></i>কন্টেন্ট সুরক্ষা</h6></div>
                            <div class="s-card-body">
                                <div class="toggle-row">
                                    <div>
                                        <div class="fw-600" style="font-size:.9rem;">লেসন কন্টেন্ট সুরক্ষা</div>
                                        <small class="text-muted">চালু করলে শিক্ষার্থীরা লেসন কপি করতে, রাইট-ক্লিক করতে বা DevTools দিয়ে কন্টেন্ট চুরি করতে পারবে না। শুধুমাত্র প্রোডাকশনে কার্যকর হবে।</small>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox"
                                            :checked="form.content_protection === '1'"
                                            @change="form.content_protection = $event.target.checked ? '1' : '0'">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary fw-600" :disabled="form.processing">
                                <i class="bi bi-check2 me-1"></i>সিকিউরিটি সেটিংস সেভ করুন
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Payment -->
                <div v-show="activeTab === 'payment'">
                    <form @submit.prevent="save('payment')">
                        <div class="s-card mb-4">
                            <div class="s-card-hd">
                                <h6 class="fw-700 mb-0">
                                    <i class="bi bi-credit-card me-2 text-primary"></i>পেমেন্ট মেথড
                                </h6>
                            </div>
                            <div class="s-card-body">
                                <p class="text-muted mb-3" style="font-size:.9rem;">
                                    এখানে দেয়া নম্বরগুলো checkout পেজে স্বয়ংক্রিয়ভাবে দেখানো হয়।
                                </p>

                                <div v-if="form.payment_methods?.length" class="d-grid gap-3">
                                    <div
                                        v-for="(method, index) in form.payment_methods"
                                        :key="method.id"
                                        class="payment-admin-card"
                                    >
                                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                            <div>
                                                <div class="fw-700">{{ method.name_bn }}</div>
                                                <div class="text-muted" style="font-size:.82rem;">Slug: {{ method.slug }}</div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge" :class="method.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                                                    {{ method.is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                                <div class="form-check form-switch mb-0">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        :checked="method.is_active"
                                                        @change="form.payment_methods[index].is_active = $event.target.checked"
                                                    >
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label fw-600">নম্বর</label>
                                                <input
                                                    v-model="form.payment_methods[index].account_number"
                                                    type="text"
                                                    class="form-control"
                                                    placeholder="01712-345678"
                                                >
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label fw-600">ক্রম</label>
                                                <input
                                                    v-model="form.payment_methods[index].sort_order"
                                                    type="number"
                                                    class="form-control"
                                                    min="0"
                                                >
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-600">Checkout নির্দেশনা</label>
                                                <textarea
                                                    v-model="form.payment_methods[index].instructions"
                                                    class="form-control"
                                                    rows="3"
                                                    placeholder="কীভাবে টাকা পাঠাতে হবে তা লিখুন..."
                                                ></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="text-muted text-center py-4">
                                    Payment method পাওয়া যায়নি।
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary fw-600" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-check2 me-1"></i>পেমেন্ট সেটিংস সেভ করুন
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Pricing -->
                <div v-show="activeTab === 'pricing'">
                    <form @submit.prevent="save('pricing')">
                        <div class="s-card mb-4">
                            <div class="s-card-hd"><h6 class="fw-700 mb-0"><i class="bi bi-tag me-2 text-primary"></i>সাবস্ক্রিপশন মেয়াদ সেটিংস (দিন হিসেবে)</h6></div>
                            <div class="s-card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-600">ফ্রি ট্রায়াল মেয়াদ (দিন)</label>
                                        <input v-model="form.subscription_duration_trial" type="number" class="form-control" min="1">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-600">মাসিক সাবস্ক্রিপশন মেয়াদ (দিন)</label>
                                        <input v-model="form.subscription_duration_monthly" type="number" class="form-control" min="1">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-600">৬ মাসের সাবস্ক্রিপশন মেয়াদ (দিন)</label>
                                        <input v-model="form.subscription_duration_yearly" type="number" class="form-control" min="1">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Night Checkout Restriction -->
                        <div class="s-card mb-4 mt-4">
                            <div class="s-card-hd"><h6 class="fw-700 mb-0"><i class="bi bi-moon me-2 text-primary"></i>রাতকালীন সাবস্ক্রিপশন নিয়ন্ত্রণ (ম্যানুয়াল পেমেন্ট)</h6></div>
                            <div class="s-card-body">
                                <div class="toggle-row" :class="{ 'border-bottom pb-3 mb-3': form.night_checkout_disabled === '1' }">
                                    <div>
                                        <div class="fw-600" style="font-size:.9rem;">রাতে সাবস্ক্রিপশন বন্ধ রাখুন</div>
                                        <small class="text-muted">চালু করলে রাতকালীন সময়ে (পেমেন্ট ভেরিফিকেশন বন্ধ থাকায়) ব্যবহারকারীরা পেমেন্ট ও সাবস্ক্রিপশন সম্পন্ন করতে পারবে না।</small>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox"
                                            :checked="form.night_checkout_disabled === '1'"
                                            @change="form.night_checkout_disabled = $event.target.checked ? '1' : '0'">
                                    </div>
                                </div>
                                <div class="row g-3" v-if="form.night_checkout_disabled === '1'">
                                    <div class="col-md-6">
                                        <label class="form-label fw-600">নিষেধাজ্ঞা শুরুর সময় (BD Time)</label>
                                        <input v-model="form.night_checkout_start" type="time" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-600">নিষেধাজ্ঞা শেষের সময় (BD Time)</label>
                                        <input v-model="form.night_checkout_end" type="time" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary fw-600" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-check2 me-1"></i>মেয়াদ সেটিংস সেভ করুন
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Social OAuth Logins -->
                <div v-show="activeTab === 'social'">
                    <form @submit.prevent="save('social')">
                        <!-- Google Settings Card -->
                        <div class="s-card mb-4">
                            <div class="s-card-hd d-flex justify-content-between align-items-center">
                                <h6 class="fw-700 mb-0">
                                    <i class="bi bi-google me-2 text-danger"></i>Google OAuth সেটিংস
                                </h6>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox"
                                        :checked="form.google_auth_enabled === '1'"
                                        @change="form.google_auth_enabled = $event.target.checked ? '1' : '0'">
                                </div>
                            </div>
                            <div class="s-card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-600">Google Client ID</label>
                                        <input v-model="form.google_client_id" type="text" class="form-control" placeholder="123456...apps.googleusercontent.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-600">Google Client Secret</label>
                                        <input v-model="form.google_client_secret" type="password" class="form-control" placeholder="••••••••">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-600">Google Redirect URI (ঐচ্ছিক - খালি রাখলে স্বয়ংক্রিয়ভাবে তৈরি হবে)</label>
                                        <input v-model="form.google_redirect_uri" type="text" class="form-control" placeholder="https://laracodevault.com/auth/google/callback">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Facebook Settings Card -->
                        <div class="s-card mb-4">
                            <div class="s-card-hd d-flex justify-content-between align-items-center">
                                <h6 class="fw-700 mb-0">
                                    <i class="bi bi-facebook me-2 text-primary"></i>Facebook OAuth সেটিংস
                                </h6>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox"
                                        :checked="form.facebook_auth_enabled === '1'"
                                        @change="form.facebook_auth_enabled = $event.target.checked ? '1' : '0'">
                                </div>
                            </div>
                            <div class="s-card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-600">Facebook App ID (Client ID)</label>
                                        <input v-model="form.facebook_client_id" type="text" class="form-control" placeholder="4733500003604027">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-600">Facebook App Secret</label>
                                        <input v-model="form.facebook_client_secret" type="password" class="form-control" placeholder="••••••••">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-600">Facebook Redirect URI (ঐচ্ছিক - খালি রাখলে স্বয়ংক্রিয়ভাবে তৈরি হবে)</label>
                                        <input v-model="form.facebook_redirect_uri" type="text" class="form-control" placeholder="https://laracodevault.com/auth/facebook/callback">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary fw-600" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-check2 me-1"></i>সোশ্যাল লগইন সেটিংস সেভ করুন
                            </button>
                        </div>
                    </form>
                </div>

                <!-- AI & Narration -->
                <div v-show="activeTab === 'ai'">
                    <form @submit.prevent="save('ai')">
                        <div class="s-card mb-4">
                            <div class="s-card-hd d-flex justify-content-between align-items-center">
                                <h6 class="fw-700 mb-0">
                                    <i class="bi bi-soundwave me-2 text-success"></i>Google Gemini TTS অডিও ন্যারেশন কনফিগারেশন
                                </h6>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox"
                                        :checked="form.lesson_narration_enabled === '1'"
                                        @change="form.lesson_narration_enabled = $event.target.checked ? '1' : '0'">
                                </div>
                            </div>
                            <div class="s-card-body">
                                <p class="text-muted mb-3" style="font-size:.88rem;">
                                    লেসনগুলোকে প্রাকৃতিক বাংলা কণ্ঠে রূপান্তর করতে Google Gemini TTS ব্যবহৃত হয়। উৎপন্ন অডিও ফাইলগুলো স্ট্যান্ডার্ড <code>.wav</code> ফরম্যাটে সংরক্ষিত হয়।
                                </p>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-600">Gemini API Key</label>
                                        <div class="input-group">
                                            <input
                                                v-model="form.gemini_api_key"
                                                :type="showApiKey ? 'text' : 'password'"
                                                class="form-control font-monospace"
                                                placeholder="AIzaSy... / AQ..."
                                            >
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary"
                                                @click="showApiKey = !showApiKey"
                                                :title="showApiKey ? 'হাইড করুন' : 'দেখান'"
                                            >
                                                <i class="bi" :class="showApiKey ? 'bi-eye-slash' : 'bi-eye'"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">খালি রাখলে <code>.env</code> এর <code>GEMINI_API_KEY</code> ব্যবহৃত হবে।</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-600">Gemini TTS মডেল</label>
                                        <input
                                            v-model="form.gemini_tts_model"
                                            type="text"
                                            class="form-control"
                                            placeholder="gemini-2.5-flash-preview-tts"
                                        >
                                        <small class="text-muted">ডিফল্ট: <code>gemini-2.5-flash-preview-tts</code></small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-600">ডিফল্ট বাংলা ভয়েস</label>
                                        <select v-model="form.gemini_tts_voice_name" class="form-select">
                                            <option value="Kore">Kore (মহিলা ভয়েস - রেকমেন্ডেড)</option>
                                            <option value="Aoede">Aoede (মহিলা ভয়েস - স্পষ্ট)</option>
                                            <option value="Leda">Leda (মহিলা ভয়েস)</option>
                                            <option value="Zephyr">Zephyr (মহিলা ভয়েস)</option>
                                            <option value="Puck">Puck (পুরুষ ভয়েস)</option>
                                            <option value="Fenrir">Fenrir (গম্ভীর পুরুষ ভয়েস)</option>
                                            <option value="Charon">Charon (শান্ত পুরুষ ভয়েস)</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-600">Gemini TTS প্রম্পট (Prompt)</label>
                                        <textarea
                                            v-model="form.gemini_tts_prompt"
                                            class="form-control"
                                            rows="3"
                                            placeholder="Read the following educational lesson text clearly, smoothly, and naturally in Bengali. Pronounce English technical terms, software concepts, and Bengali sentences fluently without skipping words or halting on technical names."
                                        ></textarea>
                                        <small class="text-muted">ডিফল্ট প্রম্পট ব্যবহার করতে চাইলে এটি খালি রাখুন।</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Google AI Studio Rate Limits & Quota Safeguards Card -->
                        <div class="s-card mb-4">
                            <div class="s-card-hd">
                                <h6 class="fw-700 mb-0">
                                    <i class="bi bi-speedometer2 me-2 text-warning"></i>Google AI Studio রেট লিমিট ও কোটা সুরক্ষা
                                </h6>
                            </div>
                            <div class="s-card-body">
                                <div class="p-3 mb-3 bg-light border rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div style="font-size:.88rem;" class="text-muted">
                                        Google AI Studio-এর ফ্রি টিয়ারে প্রতি মিনিটে এবং দৈনিক নির্দিষ্ট রিকোয়েস্ট সীমা (RPM/RPD) থাকে। কোনো কারণে রেট লিমিট হিট হলে সিস্টেম তা স্বয়ংক্রিয়ভাবে অডিট লগে রেকর্ড করবে।
                                    </div>
                                    <a
                                        href="https://aistudio.google.com/docs/rate-limits"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="btn btn-sm btn-outline-secondary fw-600 d-inline-flex align-items-center gap-1"
                                    >
                                        <i class="bi bi-box-arrow-up-right"></i>
                                        <span>Rate Limits গাইডলাইন</span>
                                    </a>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-600">সর্বোচ্চ রিকোয়েস্ট / মিনিট (RPM)</label>
                                        <input
                                            v-model="form.gemini_tts_max_rpm"
                                            type="number"
                                            class="form-control"
                                            min="1"
                                            max="120"
                                            placeholder="10"
                                        >
                                        <small class="text-muted">ডিফল্ট: <code>10</code> RPM</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-600">সর্বোচ্চ রিকোয়েস্ট / দিন (RPD)</label>
                                        <input
                                            v-model="form.gemini_tts_max_rpd"
                                            type="number"
                                            class="form-control"
                                            min="1"
                                            max="5000"
                                            placeholder="100"
                                        >
                                        <small class="text-muted">ডিফল্ট: <code>100</code> RPD</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-600">চাঙ্ক প্রতি বিলম্ব (সেকেন্ড)</label>
                                        <input
                                            v-model="form.gemini_tts_chunk_delay"
                                            type="number"
                                            class="form-control"
                                            min="0"
                                            max="30"
                                            placeholder="3"
                                        >
                                        <small class="text-muted">মাল্টি-চাঙ্ক লেসনে থ্রটলিং বিলম্ব</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-600">সর্বোচ্চ চাঙ্ক সাইজ</label>
                                        <input
                                            v-model="form.gemini_tts_chunk_chars"
                                            type="number"
                                            class="form-control"
                                            min="500"
                                            max="5000"
                                            placeholder="850"
                                        >
                                        <small class="text-muted">ডিফল্ট: <code>850</code> ক্যারেক্টার</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary fw-600" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-check2 me-1"></i>AI সেটিংস সেভ করুন
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Other tabs placeholder -->
                <div v-show="!['general','payment','email','seo','security','pricing','social','ai'].includes(activeTab)">
                    <div class="s-card">
                        <div class="s-card-body text-center py-5 text-muted">
                            <i class="bi bi-tools fs-2 mb-3 d-block" style="opacity:.3;"></i>
                            এই সেকশন শীঘ্রই আসছে।
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useNotify } from '@/composables/useNotify.js';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
    paymentMethods: { type: Array, default: () => [] },
});

const { toast } = useNotify();
const activeTab = ref('general');
const showApiKey = ref(false);

const tabs = [
    { id: 'general',       label: 'সাধারণ সেটিংস',  icon: 'bi bi-sliders' },
    { id: 'payment',       label: 'পেমেন্ট সেটিংস',  icon: 'bi bi-credit-card' },
    { id: 'pricing',       label: 'মূল্য পরিকল্পনা',  icon: 'bi bi-tag' },
    { id: 'social',        label: 'সোশ্যাল লগইন',    icon: 'bi bi-person-badge' },
    { id: 'ai',            label: 'AI ও অডিও ন্যারেশন', icon: 'bi bi-soundwave' },
    { id: 'email',         label: 'ইমেইল সেটিংস',    icon: 'bi bi-envelope' },
    { id: 'notifications', label: 'নোটিফিকেশন',       icon: 'bi bi-bell' },
    { id: 'security',      label: 'নিরাপত্তা',         icon: 'bi bi-shield-lock' },
    { id: 'seo',           label: 'SEO সেটিংস',       icon: 'bi bi-search' },
];

const generalToggles = [
    { key: 'maintenance_mode',   label: 'মেইনটেন্যান্স মোড',    desc: 'চালু করলে ব্যবহারকারীরা সাইটে প্রবেশ করতে পারবেন না' },
    { key: 'allow_registration',  label: 'নতুন ইউজার রেজিস্ট্রেশন',     desc: 'নতুন ব্যবহারকারীদের অ্যাকাউন্ট নিবন্ধন করার অনুমতি দিন' },
    { key: 'email_verification', label: 'ইমেইল যাচাইকরণ',        desc: 'নিবন্ধনের পর ইমেইল যাচাই বাধ্যতামূলক করুন' },
    { key: 'guest_preview',      label: 'গেস্ট প্রিভিউ',          desc: 'অ-নিবন্ধিত ব্যবহারকারীরা প্রথম ২টি লেসন দেখতে পাবেন' },
    { key: 'search_enabled',      label: 'লেসন সার্চ অপশন',          desc: 'প্রিমিয়াম শিক্ষার্থীদের জন্য সাইট-ওয়াইড লেসন সার্চ অপশন চালু করুন' },
];

const form = useForm({
    // General
    site_name: props.settings.site_name ?? 'লারাকোডভল্ট',
    site_tagline: props.settings.site_tagline ?? '',
    contact_email: props.settings.contact_email ?? '',
    support_phone: props.settings.support_phone ?? '',
    site_description: props.settings.site_description ?? '',
    fb_group_link: props.settings.fb_group_link ?? '',
    maintenance_mode: props.settings.maintenance_mode ?? '0',
    maintenance_banner_text: props.settings.maintenance_banner_text ?? 'সাইটটি বর্তমানে উন্নয়নশীল পর্যায়ে রয়েছে, অনুগ্রহ করে পরবর্তীতে আবার ভিজিট করুন। ধন্যবাদ!',
    allow_registration: props.settings.allow_registration ?? '1',
    email_verification: props.settings.email_verification ?? '1',
    guest_preview: props.settings.guest_preview ?? '1',
    search_enabled: props.settings.search_enabled ?? '1',
    // Email
    smtp_host: props.settings.smtp_host ?? '',
    smtp_port: props.settings.smtp_port ?? '587',
    smtp_encryption: props.settings.smtp_encryption ?? 'tls',
    smtp_username: props.settings.smtp_username ?? '',
    smtp_password: props.settings.smtp_password ?? '',
    mail_from_name: props.settings.mail_from_name ?? '',
    mail_from_address: props.settings.mail_from_address ?? '',
    // SEO
    seo_title: props.settings.seo_title ?? '',
    seo_description: props.settings.seo_description ?? '',
    seo_keywords: props.settings.seo_keywords ?? '',
    google_analytics_id: props.settings.google_analytics_id ?? '',
    // Security
    content_protection: props.settings.content_protection ?? '0',
    // Social Logins
    google_auth_enabled: props.settings.google_auth_enabled ?? '1',
    google_client_id: props.settings.google_client_id ?? '',
    google_client_secret: props.settings.google_client_secret ?? '',
    google_redirect_uri: props.settings.google_redirect_uri ?? '',
    facebook_auth_enabled: props.settings.facebook_auth_enabled ?? '1',
    facebook_client_id: props.settings.facebook_client_id ?? '',
    facebook_client_secret: props.settings.facebook_client_secret ?? '',
    facebook_redirect_uri: props.settings.facebook_redirect_uri ?? '',
    // AI & Narration
    gemini_api_key: props.settings.gemini_api_key ?? '',
    gemini_tts_model: props.settings.gemini_tts_model ?? 'gemini-2.5-flash-preview-tts',
    gemini_tts_voice_name: props.settings.gemini_tts_voice_name ?? 'Kore',
    gemini_tts_prompt: props.settings.gemini_tts_prompt ?? '',
    lesson_narration_enabled: props.settings.lesson_narration_enabled ?? '1',
    gemini_tts_max_rpm: props.settings.gemini_tts_max_rpm ?? '10',
    gemini_tts_max_rpd: props.settings.gemini_tts_max_rpd ?? '100',
    gemini_tts_chunk_delay: props.settings.gemini_tts_chunk_delay ?? '3',
    gemini_tts_chunk_chars: props.settings.gemini_tts_chunk_chars ?? '850',
    // Pricing
    subscription_duration_trial: props.settings.subscription_duration_trial ?? '3',
    subscription_duration_monthly: props.settings.subscription_duration_monthly ?? '30',
    subscription_duration_yearly: props.settings.subscription_duration_yearly ?? '180',
    night_checkout_disabled: props.settings.night_checkout_disabled ?? '0',
    night_checkout_start: props.settings.night_checkout_start ?? '23:00',
    night_checkout_end: props.settings.night_checkout_end ?? '07:00',
    payment_methods: (props.paymentMethods ?? []).map((method) => ({
        id: method.id,
        name_bn: method.name_bn,
        slug: method.slug,
        account_number: method.account_number ?? '',
        instructions: method.instructions ?? '',
        is_active: !!method.is_active,
        sort_order: method.sort_order ?? 0,
    })),
});

function save(section) {
    form.post(route('admin.settings.update'), {
        onSuccess: () => toast.success('সেটিংস সংরক্ষিত হয়েছে!'),
        onError:   () => toast.error('সংরক্ষণ করতে সমস্যা হয়েছে।'),
        preserveScroll: true,
    });
}
</script>

<style scoped>
.s-nav-card { background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:.5rem; }
.s-nav-btn { display:flex;align-items:center;gap:.65rem;width:100%;padding:.75rem 1rem;border:none;background:none;border-radius:8px;color:#6b7280;font-size:.9rem;font-weight:500;text-align:left;cursor:pointer;margin-bottom:2px;transition:background .2s,color .2s; }
.s-nav-btn:hover { background:#f1f5f9;color:#1a2e22; }
.s-nav-btn.active { background:#2d6a4f;color:#fff;font-weight:600; }
.s-nav-icon { width:32px;height:32px;border-radius:7px;background:rgba(0,0,0,.06);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0; }
.s-nav-btn.active .s-nav-icon { background:rgba(255,255,255,.18); }

.s-card { background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden; }
.s-card-hd { padding:.9rem 1.25rem;border-bottom:1px solid #f1f5f9; }
.s-card-body { padding:1.25rem; }

.payment-admin-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1rem;
    background: linear-gradient(180deg, #fff 0%, #fbfcfb 100%);
}

.toggle-row { display:flex;justify-content:space-between;align-items:center;padding:.75rem 0;gap:1rem; }
.toggle-row:last-child { border-bottom:none !important; }
</style>
