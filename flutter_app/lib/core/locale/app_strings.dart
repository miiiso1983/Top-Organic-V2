/// قاموس ترجمة بسيط (عربي/إنجليزي) للشاشات المدعومة حالياً بالترجمة.
/// يُستخدم عبر LocaleProvider.t(key) بدلاً من نظام Flutter ARB الرسمي
/// لتفادي إعادة بناء كامل مسار الترجمة في هذه المرحلة، مع إمكانية التوسّع لاحقاً.
class AppStrings {
  AppStrings._();

  static const Map<String, Map<String, String>> _values = {
    // عام
    'profile_title': {'ar': 'الملف الشخصي', 'en': 'Profile'},
    'language': {'ar': 'اللغة', 'en': 'Language'},
    'language_arabic': {'ar': 'العربية', 'en': 'Arabic'},
    'language_english': {'ar': 'الإنجليزية', 'en': 'English'},
    'save': {'ar': 'حفظ', 'en': 'Save'},
    'cancel': {'ar': 'إلغاء', 'en': 'Cancel'},
    'required_field': {'ar': 'مطلوب', 'en': 'Required'},
    'retry': {'ar': 'إعادة المحاولة', 'en': 'Retry'},

    // تغيير كلمة المرور
    'change_password': {'ar': 'تغيير كلمة المرور', 'en': 'Change Password'},
    'current_password': {'ar': 'كلمة المرور الحالية', 'en': 'Current Password'},
    'new_password': {'ar': 'كلمة المرور الجديدة', 'en': 'New Password'},
    'confirm_password': {'ar': 'تأكيد كلمة المرور', 'en': 'Confirm Password'},
    'password_min_length': {
      'ar': 'يجب ألا تقل عن 6 أحرف',
      'en': 'Must be at least 6 characters',
    },
    'password_mismatch': {
      'ar': 'كلمتا المرور غير متطابقتين',
      'en': 'Passwords do not match',
    },
    'password_changed_ok': {
      'ar': 'تم تغيير كلمة المرور بنجاح',
      'en': 'Password changed successfully',
    },

    // السكن
    'housing_title': {'ar': 'إدارة السكن', 'en': 'Housing Management'},
    'housing_site': {'ar': 'الموقع', 'en': 'Site'},
    'housing_apartment': {'ar': 'الشقة', 'en': 'Apartment'},
    'housing_room': {'ar': 'الغرفة', 'en': 'Room'},
    'housing_no_assignment': {
      'ar': 'لا يوجد سكن محدد',
      'en': 'No housing assigned',
    },
    'housing_edit': {'ar': 'تعديل السكن', 'en': 'Edit Housing'},
    'housing_updated_ok': {
      'ar': 'تم تحديث بيانات السكن بنجاح',
      'en': 'Housing info updated successfully',
    },
    'housing_select_site_first': {
      'ar': 'اختر الموقع أولاً',
      'en': 'Select a site first',
    },
    'housing_select_apartment_first': {
      'ar': 'اختر الشقة أولاً',
      'en': 'Select an apartment first',
    },
    'housing_available_beds': {'ar': 'الأسرة المتاحة', 'en': 'Available beds'},
    'housing_load_error': {
      'ar': 'تعذر تحميل بيانات السكن',
      'en': 'Could not load housing data',
    },
    'housing_access_denied': {
      'ar': 'هذه الميزة متاحة لقسم الحراسة فقط',
      'en': 'This feature is available for the security department only',
    },
    'housing_search_hint': {
      'ar': 'بحث بالاسم أو القسم أو الموقع...',
      'en': 'Search by name, department, or site...',
    },
    'housing_no_results': {'ar': 'لا توجد نتائج', 'en': 'No results found'},

    // عام (إضافي)
    'logout': {'ar': 'تسجيل الخروج', 'en': 'Logout'},

    // عناصر التنقل
    'nav_dashboard': {'ar': 'الرئيسية', 'en': 'Home'},
    'nav_notifications': {'ar': 'الإشعارات', 'en': 'Notifications'},
    'nav_approvals': {'ar': 'الطلبات والموافقات', 'en': 'Requests & Approvals'},
    'nav_archive': {'ar': 'الأرشيف', 'en': 'Archive'},
    'nav_sales': {'ar': 'المبيعات', 'en': 'Sales'},
    'nav_kitchen': {'ar': 'المطبخ', 'en': 'Kitchen'},
    'nav_inventory': {'ar': 'المخزون', 'en': 'Inventory'},
    'nav_purchasing': {'ar': 'المشتريات', 'en': 'Purchasing'},
    'nav_hr': {'ar': 'الموارد البشرية', 'en': 'Human Resources'},
    'nav_clock': {'ar': 'بصمة الحضور', 'en': 'Attendance'},
    'nav_clock_short': {'ar': 'البصمة', 'en': 'Attendance'},
    'nav_call_center': {'ar': 'كول سنتر', 'en': 'Call Center'},
    'nav_reservations': {'ar': 'الحجوزات', 'en': 'Reservations'},
    'nav_tasks': {'ar': 'المهام', 'en': 'Tasks'},
    'nav_assets': {'ar': 'العهدة والأصول', 'en': 'Custody & Assets'},
    'nav_reports': {'ar': 'التقارير', 'en': 'Reports'},
    'nav_housing': {'ar': 'السكن', 'en': 'Housing'},
    'nav_mail': {'ar': 'البريد الداخلي', 'en': 'Internal Mail'},

    // تسجيل الدخول
    'login_subtitle': {
      'ar': 'نظام إدارة المطاعم',
      'en': 'Restaurant Management System',
    },
    'login_username_label': {'ar': 'اسم المستخدم', 'en': 'Username'},
    'login_password_label': {'ar': 'كلمة المرور', 'en': 'Password'},
    'login_submit': {'ar': 'تسجيل الدخول', 'en': 'Login'},

    // لوحة التحكم
    'dashboard_load_error': {
      'ar': 'تعذر تحميل بيانات لوحة التحكم',
      'en': 'Could not load dashboard data',
    },
    'dashboard_today_orders': {'ar': 'طلبات اليوم', 'en': "Today's Orders"},
    'dashboard_today_sales': {'ar': 'مبيعات اليوم', 'en': "Today's Sales"},
    'dashboard_cash_balance': {'ar': 'رصيد الصندوق', 'en': 'Cash Balance'},
    'dashboard_low_stock': {'ar': 'نواقص المخزون', 'en': 'Low Stock Items'},
    'dashboard_pending_approvals': {
      'ar': 'موافقات معلّقة',
      'en': 'Pending Approvals',
    },
    'dashboard_no_kpis': {
      'ar': 'لا توجد مؤشرات متاحة لصلاحياتك',
      'en': 'No indicators available for your permissions',
    },

    // الإشعارات
    'notifications_load_error': {
      'ar': 'تعذر تحميل الإشعارات',
      'en': 'Could not load notifications',
    },
    'notifications_empty': {'ar': 'لا توجد إشعارات', 'en': 'No notifications'},
    'notifications_detail_title': {
      'ar': 'تفاصيل الإشعار',
      'en': 'Notification Details',
    },
    'notifications_request_type': {'ar': 'نوع الطلب', 'en': 'Request Type'},
    'notifications_request_no': {'ar': 'رقم الطلب', 'en': 'Request No.'},
    'notifications_requester': {'ar': 'الطالب', 'en': 'Requester'},
    'notifications_hours_pending': {'ar': 'مدة الانتظار', 'en': 'Waiting Time'},
    'notifications_hours_suffix': {'ar': 'ساعة', 'en': 'hours'},
    'notifications_status': {'ar': 'الحالة', 'en': 'Status'},
    'notifications_step': {'ar': 'الخطوة', 'en': 'Step'},
    'notifications_date': {'ar': 'التاريخ', 'en': 'Date'},
    'notifications_type': {'ar': 'نوع الإشعار', 'en': 'Notification Type'},
    'notifications_close': {'ar': 'إغلاق', 'en': 'Close'},
    'notifications_open': {'ar': 'فتح', 'en': 'Open'},
    'notifications_action_pending_approval': {
      'ar': 'طلب موافقة',
      'en': 'Approval Request',
    },
    'notifications_action_pending_reminder': {
      'ar': 'تذكير طلب معلق',
      'en': 'Pending Request Reminder',
    },
    'notifications_action_pending_reminder_manager': {
      'ar': 'تذكير مدير مباشر',
      'en': 'Direct Manager Reminder',
    },
    'notifications_action_approved': {'ar': 'موافقة', 'en': 'Approved'},
    'notifications_action_step_approved': {
      'ar': 'موافقة على مرحلة',
      'en': 'Step Approved',
    },
    'notifications_action_rejected': {'ar': 'رفض', 'en': 'Rejected'},
    'notifications_action_returned_for_edit': {
      'ar': 'طلب تعديل',
      'en': 'Returned for Edit',
    },
    'notifications_action_escalated': {'ar': 'تصعيد', 'en': 'Escalated'},
    'notifications_action_status_update': {
      'ar': 'تحديث حالة',
      'en': 'Status Update',
    },

    // البريد الداخلي (مراسلات حقيقية بين مستخدمي النظام)
    'mail_title': {'ar': 'البريد الداخلي', 'en': 'Internal Mail'},
    'mail_load_error': {'ar': 'تعذر تحميل البريد', 'en': 'Could not load mail'},
    'mail_empty': {'ar': 'لا توجد رسائل واردة', 'en': 'No inbox messages'},
    'mail_sent_empty': {'ar': 'لا توجد رسائل مُرسلة', 'en': 'No sent messages'},
    'mail_inbox_tab': {'ar': 'الوارد', 'en': 'Inbox'},
    'mail_sent_tab': {'ar': 'الصادر', 'en': 'Sent'},
    'mail_compose_fab': {'ar': 'رسالة جديدة', 'en': 'New message'},
    'mail_compose_title': {'ar': 'رسالة بريد داخلية جديدة', 'en': 'New internal mail'},
    'mail_from_label': {'ar': 'من', 'en': 'From'},
    'mail_to_label': {'ar': 'إلى', 'en': 'To'},
    'mail_date_label': {'ar': 'التاريخ', 'en': 'Date'},
    'mail_close': {'ar': 'إغلاق', 'en': 'Close'},
    'mail_reply_btn': {'ar': 'رد', 'en': 'Reply'},
    'mail_delete_btn': {'ar': 'حذف', 'en': 'Delete'},
    'mail_delete_confirm_title': {'ar': 'حذف الرسالة', 'en': 'Delete message'},
    'mail_delete_confirm_body': {
      'ar': 'هل أنت متأكد من حذف هذه الرسالة؟',
      'en': 'Are you sure you want to delete this message?',
    },
    'mail_recipients_label': {'ar': 'المستلمون', 'en': 'Recipients'},
    'mail_recipients_hint': {'ar': 'ابحث عن مستخدم...', 'en': 'Search users...'},
    'mail_recipients_required': {
      'ar': 'يجب اختيار مستلم واحد على الأقل',
      'en': 'Please select at least one recipient',
    },
    'mail_users_load_error': {'ar': 'تعذر تحميل قائمة المستخدمين', 'en': 'Could not load users'},
    'mail_subject_label': {'ar': 'الموضوع', 'en': 'Subject'},
    'mail_subject_hint': {'ar': 'موضوع الرسالة', 'en': 'Message subject'},
    'mail_body_label': {'ar': 'نص الرسالة', 'en': 'Message'},
    'mail_body_hint': {'ar': 'اكتب نص الرسالة...', 'en': 'Write your message...'},
    'mail_subject_body_required': {
      'ar': 'الموضوع ونص الرسالة مطلوبان',
      'en': 'Subject and message are required',
    },
    'mail_send_btn': {'ar': 'إرسال', 'en': 'Send'},
    'mail_send_error': {'ar': 'تعذر إرسال الرسالة', 'en': 'Could not send message'},
    'mail_recipients_optional_note': {
      'ar': 'اختر مستلماً فرداً أو أكثر، أو قسماً، أو مجموعة بريد (مطلوب واحد على الأقل)',
      'en': 'Pick one or more individuals, a department, or a mail group (at least one required)',
    },
    'mail_departments_label': {'ar': 'الأقسام', 'en': 'Departments'},
    'mail_departments_hint': {
      'ar': 'إرسال لجميع موظفي القسم النشطين',
      'en': 'Send to all active employees in the department',
    },
    'mail_departments_load_error': {'ar': 'تعذر تحميل قائمة الأقسام', 'en': 'Could not load departments'},
    'mail_groups_label': {'ar': 'مجموعات البريد', 'en': 'Mail groups'},
    'mail_groups_load_error': {'ar': 'تعذر تحميل مجموعات البريد', 'en': 'Could not load mail groups'},
    'mail_groups_manage_btn': {'ar': 'إدارة المجموعات', 'en': 'Manage groups'},
    'mail_attachments_label': {'ar': 'المرفقات', 'en': 'Attachments'},
    'mail_attach_btn': {'ar': 'إرفاق صور', 'en': 'Attach photos'},
    'mail_attachments_count': {'ar': 'مرفقات', 'en': 'attachments'},
    'mail_view_attachment': {'ar': 'عرض المرفق', 'en': 'View attachment'},
    'mail_groups_screen_title': {'ar': 'مجموعات البريد', 'en': 'Mail Groups'},
    'mail_groups_empty': {'ar': 'لا توجد مجموعات بعد', 'en': 'No groups yet'},
    'mail_group_new_fab': {'ar': 'مجموعة جديدة', 'en': 'New group'},
    'mail_group_new_title': {'ar': 'مجموعة بريد جديدة', 'en': 'New mail group'},
    'mail_group_edit_title': {'ar': 'تعديل المجموعة', 'en': 'Edit group'},
    'mail_group_name_label': {'ar': 'اسم المجموعة', 'en': 'Group name'},
    'mail_group_name_required': {'ar': 'اسم المجموعة مطلوب', 'en': 'Group name is required'},
    'mail_group_members_label': {'ar': 'الأعضاء', 'en': 'Members'},
    'mail_group_members_count': {'ar': 'عضو', 'en': 'members'},
    'mail_group_save_btn': {'ar': 'حفظ', 'en': 'Save'},
    'mail_group_delete_confirm_title': {'ar': 'حذف المجموعة', 'en': 'Delete group'},
    'mail_group_delete_confirm_body': {
      'ar': 'هل أنت متأكد من حذف هذه المجموعة؟',
      'en': 'Are you sure you want to delete this group?',
    },
    'mail_group_not_owner': {
      'ar': 'يمكن فقط لمنشئ المجموعة تعديلها أو حذفها',
      'en': 'Only the group creator can edit or delete it',
    },
    'mail_group_save_error': {'ar': 'تعذر حفظ المجموعة', 'en': 'Could not save the group'},
    'mail_group_delete_error': {'ar': 'تعذر حذف المجموعة', 'en': 'Could not delete the group'},
    'mail_target_department': {'ar': 'قسم', 'en': 'Department'},
    'mail_target_group': {'ar': 'مجموعة', 'en': 'Group'},

    // بصمة الحضور / الانصراف
    'hr_attendance_title': {
      'ar': 'بصمة الحضور / الانصراف',
      'en': 'Attendance / Departure',
    },
    'hr_attendance_load_error': {
      'ar': 'تعذر تحميل حالة الحضور',
      'en': 'Could not load attendance status',
    },
    'hr_attendance_camera_permission_title': {
      'ar': 'إذن الكاميرا مطلوب',
      'en': 'Camera Permission Required',
    },
    'hr_attendance_camera_permission_content': {
      'ar': 'تم رفض إذن الكاميرا سابقاً. هل تريد فتح إعدادات التطبيق لتفعيله؟',
      'en':
          'Camera permission was previously denied. Do you want to open app settings to enable it?',
    },
    'hr_attendance_open_settings': {
      'ar': 'فتح الإعدادات',
      'en': 'Open Settings',
    },
    'hr_attendance_camera_required': {
      'ar': 'يجب السماح بالوصول للكاميرا',
      'en': 'Camera access must be allowed',
    },
    'hr_attendance_identity_not_verified': {
      'ar': 'لم يتم التحقق من الهوية',
      'en': 'Identity not verified',
    },
    'hr_attendance_photo_required': {
      'ar': 'يجب التقاط صورة',
      'en': 'A photo must be taken',
    },
    'hr_attendance_no_reference_photo': {
      'ar': 'لا توجد صورة مرجعية. راجع المدير لرفع صورة وجهك.',
      'en':
          'No reference photo found. Ask your manager to upload your face photo.',
    },
    'hr_attendance_face_mismatch_prefix': {
      'ar': 'لا يوجد تطابق كافٍ (التشابه: ',
      'en': 'Not enough similarity (similarity: ',
    },
    'hr_attendance_face_mismatch_suffix': {
      'ar': '%). حاول مرة أخرى.',
      'en': '%). Try again.',
    },
    'hr_attendance_face_verify_failed': {
      'ar': 'فشل التحقق من الوجه',
      'en': 'Face verification failed',
    },
    'hr_attendance_checked_default': {
      'ar': 'تم التسجيل',
      'en': 'Recorded successfully',
    },
    'hr_attendance_check_failed': {
      'ar': 'فشل التسجيل',
      'en': 'Recording failed',
    },
    'hr_attendance_reference_saved': {
      'ar': 'تم حفظ الصورة المرجعية',
      'en': 'Reference photo saved',
    },
    'hr_attendance_reference_save_failed': {
      'ar': 'فشل حفظ الصورة المرجعية',
      'en': 'Failed to save reference photo',
    },
    'hr_attendance_device_not_supported': {
      'ar': 'الجهاز لا يدعم التحقق الحيوي',
      'en': 'Device does not support biometric verification',
    },
    'hr_attendance_biometric_reason': {
      'ar': 'الرجاء التحقق من هويتك لتسجيل الحضور/الانصراف',
      'en': 'Please verify your identity to record attendance/departure',
    },
    'hr_attendance_pin_reason': {
      'ar': 'الرجاء إدخال رمز PIN أو كلمة المرور للمتابعة',
      'en': 'Please enter your PIN or password to continue',
    },
    'hr_attendance_biometric_not_setup': {
      'ar': 'لم يتم إعداد التحقق الحيوي على هذا الجهاز',
      'en': 'Biometric verification is not set up on this device',
    },
    'hr_attendance_security_alert_title': {
      'ar': 'تنبيه أمان',
      'en': 'Security Alert',
    },
    'hr_attendance_continue_without_auth_suffix': {
      'ar': '. هل تريد المتابعة بدون تحقق حيوي؟',
      'en': '. Do you want to continue without biometric verification?',
    },
    'hr_attendance_continue': {'ar': 'متابعة', 'en': 'Continue'},
    'hr_attendance_allowed_radius_prefix': {
      'ar': 'النطاق المسموح: ',
      'en': 'Allowed radius: ',
    },
    'hr_attendance_allowed_radius_suffix': {'ar': ' متر', 'en': ' meters'},
    'hr_attendance_clock_in_label': {'ar': 'حضور', 'en': 'Check-in'},
    'hr_attendance_clock_out_label': {'ar': 'انصراف', 'en': 'Check-out'},
    'hr_attendance_checking': {'ar': 'جاري التحقق...', 'en': 'Verifying...'},
    'hr_attendance_check_in_button': {'ar': 'تسجيل حضور', 'en': 'Check In'},
    'hr_attendance_check_out_button': {'ar': 'تسجيل انصراف', 'en': 'Check Out'},
    'hr_attendance_geofence_notice': {
      'ar': 'يجب أن تكون ضمن النطاق الجغرافي المسموح',
      'en': 'You must be within the allowed geographic range',
    },
    'hr_attendance_upload_reference': {
      'ar': 'رفع صورة وجه مرجعية',
      'en': 'Upload Reference Face Photo',
    },

    // طلب سلفة استثنائية
    'hr_advance_title': {
      'ar': 'طلب سلفة استثنائية',
      'en': 'Exceptional Advance Request',
    },
    'hr_advance_template_unavailable': {
      'ar': 'قالب السلفة الاستثنائية غير متاح',
      'en': 'Exceptional advance template is unavailable',
    },
    'hr_advance_load_error_prefix': {
      'ar': 'تعذر تحميل البيانات: ',
      'en': 'Could not load data: ',
    },
    'hr_advance_no_salary_found': {
      'ar': 'لا يمكن إرسال الطلب: لم يُعثر على راتب مسجل للموظف',
      'en':
          'Cannot submit request: no registered salary found for the employee',
    },
    'hr_advance_amount_exceeds_prefix': {
      'ar': 'المبلغ أعلى من المسموح: ',
      'en': 'Amount exceeds the allowed limit: ',
    },
    'hr_advance_submitted_prefix': {
      'ar': 'تم إرسال الطلب: ',
      'en': 'Request sent: ',
    },
    'hr_advance_submit_failed': {
      'ar': 'فشل إرسال الطلب',
      'en': 'Failed to send request',
    },
    'hr_advance_submit_error_prefix': {
      'ar': 'تعذر إرسال الطلب: ',
      'en': 'Could not send request: ',
    },
    'hr_advance_employee_label': {'ar': 'الموظف *', 'en': 'Employee *'},
    'hr_advance_choose_employee': {'ar': 'اختر موظف', 'en': 'Choose Employee'},
    'hr_advance_search_by_name': {'ar': 'بحث بالاسم', 'en': 'Search by name'},
    'hr_advance_type_label': {'ar': 'نوع السلفة *', 'en': 'Advance Type *'},
    'hr_advance_type_exceptional': {'ar': 'استثنائية', 'en': 'Exceptional'},
    'hr_advance_type_long_term': {'ar': 'طويلة الأجل', 'en': 'Long-term'},
    'hr_advance_installments_label': {
      'ar': 'عدد الأقساط *',
      'en': 'Number of Installments *',
    },
    'hr_advance_salary_label': {'ar': 'الراتب: ', 'en': 'Salary: '},
    'hr_advance_max_label': {
      'ar': ' | الحد الأقصى (25%): ',
      'en': ' | Max (25%): ',
    },
    'hr_advance_remaining_label': {
      'ar': ' | المتبقي: ',
      'en': ' | Remaining: ',
    },
    'hr_advance_select_employee_hint': {
      'ar': 'اختر موظف لعرض الحد المسموح',
      'en': 'Select an employee to view the allowed limit',
    },
    'hr_advance_amount_label': {'ar': 'المبلغ *', 'en': 'Amount *'},
    'hr_advance_amount_invalid': {
      'ar': 'المبلغ غير صالح',
      'en': 'Invalid amount',
    },
    'hr_advance_reason_label': {'ar': 'السبب *', 'en': 'Reason *'},
    'hr_advance_submitting': {'ar': 'جاري الإرسال...', 'en': 'Sending...'},
    'hr_advance_submit_button': {'ar': 'إرسال الطلب', 'en': 'Submit Request'},

    // شاشة الموارد البشرية الرئيسية
    'hr_home_load_error': {
      'ar': 'تعذر تحميل بيانات الموظف',
      'en': 'Could not load employee data',
    },
    'hr_home_identity_not_verified': {
      'ar': 'لم يتم التحقق من الهوية',
      'en': 'Identity not verified',
    },
    'hr_home_checked_default': {
      'ar': 'تم التسجيل',
      'en': 'Recorded successfully',
    },
    'hr_home_verifying': {
      'ar': 'جاري التحقق من الهوية...',
      'en': 'Verifying identity...',
    },
    'hr_home_biometric_reason': {
      'ar': 'الرجاء التحقق من هويتك لتسجيل الحضور أو الانصراف',
      'en': 'Please verify your identity to record attendance or departure',
    },
    'hr_home_pin_reason': {
      'ar': 'الرجاء إدخال رمز PIN أو كلمة المرور للمتابعة',
      'en': 'Please enter your PIN or password to continue',
    },
    'hr_home_auth_no_hardware': {
      'ar': 'المستشعرات الحيوية غير متوفرة على هذا الجهاز',
      'en': 'Biometric sensors are not available on this device',
    },
    'hr_home_auth_not_enrolled': {
      'ar': 'لم يتم إعداد Face ID أو البصمة',
      'en': 'Face ID or fingerprint is not set up',
    },
    'hr_home_auth_no_credentials': {
      'ar': 'لم يتم إعداد رمز PIN أو كلمة المرور',
      'en': 'PIN or password is not set up',
    },
    'hr_home_auth_lockout': {
      'ar': 'تم إقفال التحقق الحيوي مؤقتاً',
      'en': 'Biometric verification is temporarily locked',
    },
    'hr_home_auth_canceled': {
      'ar': 'تم إلغاء التحقق من الهوية',
      'en': 'Identity verification was canceled',
    },
    'hr_home_auth_failed': {
      'ar': 'فشل التحقق من الهوية',
      'en': 'Identity verification failed',
    },
    'hr_home_auth_no_support': {
      'ar': 'الجهاز لا يدعم أي وسيلة تحقق',
      'en': 'This device does not support any verification method',
    },
    'hr_home_checking': {'ar': 'جاري التحقق...', 'en': 'Verifying...'},
    'hr_home_check_in': {'ar': 'تسجيل حضور', 'en': 'Check In'},
    'hr_home_check_out': {'ar': 'تسجيل انصراف', 'en': 'Check Out'},
    'hr_home_advance_button': {
      'ar': 'طلب سلفة استثنائية',
      'en': 'Exceptional Advance Request',
    },
    'hr_home_salary_button': {'ar': 'كشف الراتب', 'en': 'Salary Statement'},
    'hr_home_team_eval_button': {
      'ar': 'تقييم أداء الفريق',
      'en': 'Team Performance Evaluation',
    },
    'hr_home_recent_attendance': {
      'ar': 'سجل الحضور الأخير',
      'en': 'Recent Attendance Log',
    },
    'hr_home_leaves': {'ar': 'الإجازات', 'en': 'Leaves'},
    'hr_home_leave_to': {'ar': 'إلى', 'en': 'to'},

    // شاشة تحدي الحيوية (الكاميرا)
    'hr_liveness_instruction_blink': {
      'ar': 'غمض عينيك مرة واحدة',
      'en': 'Blink your eyes once',
    },
    'hr_liveness_instruction_smile': {
      'ar': 'ابتسم بشكل واضح',
      'en': 'Smile clearly',
    },
    'hr_liveness_instruction_turn_left': {
      'ar': 'حرّك رأسك لليسار قليلاً',
      'en': 'Turn your head slightly left',
    },
    'hr_liveness_instruction_turn_right': {
      'ar': 'حرّك رأسك لليمين قليلاً',
      'en': 'Turn your head slightly right',
    },
    'hr_liveness_instruction_default': {
      'ar': 'اتبع التعليمة',
      'en': 'Follow the instruction',
    },
    'hr_liveness_camera_permission_denied': {
      'ar':
          'لم يتم منح إذن الكاميرا. فعّل الكاميرا من إعدادات التطبيق ثم أعد المحاولة.',
      'en':
          'Camera permission was not granted. Enable the camera from app settings then try again.',
    },
    'hr_liveness_no_camera_found': {
      'ar': 'لم يتم العثور على كاميرا في هذا الجهاز',
      'en': 'No camera was found on this device',
    },
    'hr_liveness_access_denied': {
      'ar':
          'تم رفض الوصول للكاميرا. فعّل الإذن من إعدادات التطبيق ثم أعد المحاولة.',
      'en':
          'Camera access was denied. Enable the permission from app settings then try again.',
    },
    'hr_liveness_timeout': {
      'ar': 'استغرقت الكاميرا وقتاً طويلاً للتشغيل. أعد المحاولة.',
      'en': 'The camera took too long to start. Try again.',
    },
    'hr_liveness_generic_error': {
      'ar': 'تعذر تشغيل الكاميرا. أعد المحاولة أو أعد تشغيل التطبيق.',
      'en': 'Could not start the camera. Try again or restart the app.',
    },
    'hr_liveness_verified_capturing': {
      'ar': 'تم التحقق، جاري التقاط الصورة...',
      'en': 'Verified, capturing photo...',
    },
    'hr_liveness_close': {'ar': 'إغلاق', 'en': 'Close'},

    // تعيين وردية للموظف (المدير)
    'hr_shift_assign_title': {
      'ar': 'تعيين وردية للموظف',
      'en': 'Assign Shift to Employee',
    },
    'hr_shift_assign_load_error_prefix': {
      'ar': 'تعذر تحميل البيانات: ',
      'en': 'Could not load data: ',
    },
    'hr_shift_assign_fill_all_fields': {
      'ar': 'يرجى تعبئة جميع الحقول',
      'en': 'Please fill in all fields',
    },
    'hr_shift_assign_success': {
      'ar': 'تم تعيين الوردية',
      'en': 'Shift assigned successfully',
    },
    'hr_shift_assign_failed': {
      'ar': 'فشل تعيين الوردية',
      'en': 'Failed to assign shift',
    },
    'hr_shift_assign_error_prefix': {
      'ar': 'تعذر تعيين الوردية: ',
      'en': 'Could not assign shift: ',
    },
    'hr_shift_assign_employee_label': {'ar': 'الموظف *', 'en': 'Employee *'},
    'hr_shift_assign_choose_employee': {
      'ar': 'اختر الموظف',
      'en': 'Choose Employee',
    },
    'hr_shift_assign_search_hint': {
      'ar': 'ابحث بالاسم...',
      'en': 'Search by name...',
    },
    'hr_shift_assign_no_results': {
      'ar': 'لا توجد نتائج',
      'en': 'No results found',
    },
    'hr_shift_assign_no_subordinates': {
      'ar': 'لا يوجد مرؤوسون متاحون',
      'en': 'No subordinates available',
    },
    'hr_shift_assign_date_label': {
      'ar': 'تاريخ الوردية *',
      'en': 'Shift Date *',
    },
    'hr_shift_assign_pick_date': {'ar': 'اختر التاريخ', 'en': 'Select Date'},
    'hr_shift_assign_shift_label': {'ar': 'الوردية *', 'en': 'Shift *'},
    'hr_shift_assign_saving': {'ar': 'جاري الحفظ...', 'en': 'Saving...'},
    'hr_shift_assign_save_button': {'ar': 'حفظ الوردية', 'en': 'Save Shift'},

    // اعتماد العمل الإضافي (المدير)
    'hr_overtime_manager_title': {
      'ar': 'اعتماد العمل الإضافي',
      'en': 'Overtime Approval',
    },
    'hr_overtime_manager_load_error_prefix': {
      'ar': 'تعذر تحميل الطلبات: ',
      'en': 'Could not load requests: ',
    },
    'hr_overtime_manager_approved': {
      'ar': 'تمت الموافقة',
      'en': 'Approved successfully',
    },
    'hr_overtime_manager_rejected': {'ar': 'تم الرفض', 'en': 'Rejected'},
    'hr_overtime_manager_decide_error': {
      'ar': 'تعذر تنفيذ القرار',
      'en': 'Could not execute the decision',
    },
    'hr_overtime_manager_status_pending': {
      'ar': 'بانتظار المدير المباشر',
      'en': 'Awaiting direct manager',
    },
    'hr_overtime_manager_status_manager_approved': {
      'ar': 'بانتظار مدير المدير',
      'en': 'Awaiting manager\'s manager',
    },
    'hr_overtime_manager_status_fully_approved': {
      'ar': 'معتمد نهائياً',
      'en': 'Fully approved',
    },
    'hr_overtime_manager_status_rejected': {'ar': 'مرفوض', 'en': 'Rejected'},
    'hr_overtime_manager_status_expired': {
      'ar': 'منتهي الصلاحية',
      'en': 'Expired',
    },
    'hr_overtime_manager_no_attendance': {
      'ar': 'لا توجد سجلات بصمة',
      'en': 'No attendance records found',
    },
    'hr_overtime_manager_check_in_word': {'ar': 'دخول', 'en': 'In'},
    'hr_overtime_manager_check_out_word': {'ar': 'خروج', 'en': 'Out'},
    'hr_overtime_manager_no_pending': {
      'ar': 'لا توجد طلبات معلقة',
      'en': 'No pending requests',
    },
    'hr_overtime_manager_date_label': {'ar': 'التاريخ: ', 'en': 'Date: '},
    'hr_overtime_manager_hours_requested_label': {
      'ar': ' • الساعات المطلوبة: ',
      'en': ' • Requested hours: ',
    },
    'hr_overtime_manager_amount_label': {
      'ar': ' • المبلغ: ',
      'en': ' • Amount: ',
    },
    'hr_overtime_manager_actual_hours_label': {
      'ar': 'الساعات الفعلية: ',
      'en': 'Actual hours: ',
    },
    'hr_overtime_manager_biometric_label': {
      'ar': 'البصمة: ',
      'en': 'Biometric: ',
    },
    'hr_overtime_manager_matched': {
      'ar': 'مطابق للبصمة',
      'en': 'Matches biometric',
    },
    'hr_overtime_manager_not_matched': {'ar': 'غير مطابق', 'en': 'Not matched'},
    'hr_overtime_manager_reject_button': {'ar': 'رفض', 'en': 'Reject'},
    'hr_overtime_manager_approve_button': {'ar': 'موافقة', 'en': 'Approve'},

    // طلب عمل إضافي (الموظف)
    'hr_overtime_request_title': {
      'ar': 'طلب عمل إضافي',
      'en': 'Overtime Request',
    },
    'hr_overtime_request_sent': {
      'ar': 'تم إرسال طلب العمل الإضافي، يمكنك متابعته من الأرشيف',
      'en': 'Overtime request sent, you can track it from the Archive',
    },
    'hr_overtime_request_send_error': {
      'ar': 'تعذر إرسال الطلب',
      'en': 'Could not send request',
    },
    'hr_overtime_request_date_label': {
      'ar': 'تاريخ العمل الإضافي *',
      'en': 'Overtime Date *',
    },
    'hr_overtime_request_hours_label': {
      'ar': 'عدد الساعات *',
      'en': 'Number of Hours *',
    },
    'hr_overtime_request_invalid_value': {
      'ar': 'القيمة غير صالحة',
      'en': 'Invalid value',
    },
    'hr_overtime_request_reason_label': {'ar': 'السبب', 'en': 'Reason'},
    'hr_overtime_request_submitting': {
      'ar': 'جاري الإرسال...',
      'en': 'Sending...',
    },
    'hr_overtime_request_submit_button': {
      'ar': 'إرسال الطلب',
      'en': 'Submit Request',
    },

    // تفاصيل كشف الراتب
    'hr_salary_detail_load_error_prefix': {
      'ar': 'تعذر تحميل تفاصيل الراتب: ',
      'en': 'Could not load salary details: ',
    },
    'hr_salary_detail_title_prefix': {'ar': 'كشف راتب ', 'en': 'Payslip '},
    'hr_salary_detail_no_data': {
      'ar': 'لا توجد بيانات',
      'en': 'No data available',
    },
    'hr_salary_detail_earnings': {'ar': 'الاستحقاقات', 'en': 'Earnings'},
    'hr_salary_detail_base_salary': {
      'ar': 'الراتب الأساسي',
      'en': 'Base Salary',
    },
    'hr_salary_detail_raises': {'ar': 'الزيادات', 'en': 'Raises'},
    'hr_salary_detail_allowance_fallback': {'ar': 'بدل', 'en': 'Allowance'},
    'hr_salary_detail_bonuses': {'ar': 'المكافآت', 'en': 'Bonuses'},
    'hr_salary_detail_travel_tickets': {
      'ar': 'تذاكر سفر',
      'en': 'Travel Tickets',
    },
    'hr_salary_detail_overtime_prefix': {'ar': 'إضافي ', 'en': 'Overtime '},
    'hr_salary_detail_overtime_hours_suffix': {
      'ar': ' ساعة × ',
      'en': ' hr × ',
    },
    'hr_salary_detail_tips_addition': {
      'ar': 'إضافي التيبس',
      'en': 'Tips Addition',
    },
    'hr_salary_detail_total_earnings': {
      'ar': 'إجمالي الاستحقاقات',
      'en': 'Total Earnings',
    },
    'hr_salary_detail_deductions': {'ar': 'الاستقطاعات', 'en': 'Deductions'},
    'hr_salary_detail_deductions_row': {'ar': 'الخصومات', 'en': 'Deductions'},
    'hr_salary_detail_penalties': {'ar': 'الجزاءات', 'en': 'Penalties'},
    'hr_salary_detail_advances': {
      'ar': 'أقساط السلف',
      'en': 'Advance Installments',
    },
    'hr_salary_detail_long_term_advances': {
      'ar': 'سلف طويلة الأجل',
      'en': 'Long-term Advances',
    },
    'hr_salary_detail_security_deposit': {
      'ar': 'تأمين المستحقات',
      'en': 'Security Deposit',
    },
    'hr_salary_detail_insurance': {
      'ar': 'التأمينات الشهرية',
      'en': 'Monthly Insurance',
    },
    'hr_salary_detail_loadings': {
      'ar': 'التحميلات الشهرية',
      'en': 'Monthly Loadings',
    },
    'hr_salary_detail_tips': {'ar': 'التيبس', 'en': 'Tips'},
    'hr_salary_detail_social_security': {
      'ar': 'الضمان الاجتماعي',
      'en': 'Social Security',
    },
    'hr_salary_detail_housing_deduction': {
      'ar': 'خصم خدمات السكن',
      'en': 'Housing Services Deduction',
    },
    'hr_salary_detail_absence_prefix': {'ar': 'غيابات (', 'en': 'Absences ('},
    'hr_salary_detail_absence_suffix': {'ar': ' يوم)', 'en': ' days)'},
    'hr_salary_detail_tax': {'ar': 'الضريبة', 'en': 'Tax'},
    'hr_salary_detail_total_deductions': {
      'ar': 'إجمالي الاستقطاعات',
      'en': 'Total Deductions',
    },
    'hr_salary_detail_net_salary': {'ar': 'الراتب الصافي', 'en': 'Net Salary'},

    // كشف الراتب (القائمة)
    'hr_salary_load_error_prefix': {
      'ar': 'تعذر تحميل كشوف الرواتب: ',
      'en': 'Could not load payslips: ',
    },
    'hr_salary_status_approved': {'ar': 'معتمد', 'en': 'Approved'},
    'hr_salary_status_paid': {'ar': 'مصروف', 'en': 'Paid'},
    'hr_salary_status_draft': {'ar': 'قيد الإعداد', 'en': 'Draft'},
    'hr_salary_title': {'ar': 'كشف الراتب', 'en': 'Salary Statement'},
    'hr_salary_empty': {
      'ar': 'لا توجد كشوف رواتب معتمدة بعد',
      'en': 'No approved payslips yet',
    },
    'hr_salary_net_label': {'ar': 'الصافي: ', 'en': 'Net: '},

    // طلب تغيير وردية (الموظف)
    'hr_shift_request_load_error_prefix': {
      'ar': 'تعذر تحميل البيانات: ',
      'en': 'Could not load data: ',
    },
    'hr_shift_request_missing_data': {
      'ar': 'بيانات الموظف أو القالب غير متاحة',
      'en': 'Employee data or template is unavailable',
    },
    'hr_shift_request_title': {
      'ar': 'طلب تغيير وردية',
      'en': 'Shift Change Request',
    },
    'hr_shift_request_submitted_prefix': {
      'ar': 'تم إرسال الطلب: ',
      'en': 'Request sent: ',
    },
    'hr_shift_request_submit_failed': {
      'ar': 'فشل إرسال الطلب',
      'en': 'Failed to send request',
    },
    'hr_shift_request_submit_error_prefix': {
      'ar': 'تعذر إرسال الطلب: ',
      'en': 'Could not send request: ',
    },
    'hr_shift_request_date_label': {
      'ar': 'تاريخ الوردية *',
      'en': 'Shift Date *',
    },
    'hr_shift_request_pick_date': {'ar': 'اختر التاريخ', 'en': 'Select Date'},
    'hr_shift_request_shift_label': {'ar': 'الوردية *', 'en': 'Shift *'},
    'hr_shift_request_reason_label': {'ar': 'السبب *', 'en': 'Reason *'},
    'hr_shift_request_submitting': {
      'ar': 'جاري الإرسال...',
      'en': 'Sending...',
    },
    'hr_shift_request_submit_button': {
      'ar': 'إرسال الطلب',
      'en': 'Submit Request',
    },

    // نموذج تقييم المرؤوس
    'hr_eval_form_load_error_prefix': {
      'ar': 'تعذر تحميل مؤشرات الأداء: ',
      'en': 'Could not load KPIs: ',
    },
    'hr_eval_form_saved': {
      'ar': 'تم حفظ التقييم بنجاح',
      'en': 'Evaluation saved successfully',
    },
    'hr_eval_form_save_failed': {
      'ar': 'فشل حفظ التقييم',
      'en': 'Failed to save evaluation',
    },
    'hr_eval_form_save_error': {
      'ar': 'تعذر حفظ التقييم',
      'en': 'Could not save evaluation',
    },
    'hr_eval_form_title_prefix': {'ar': 'تقييم ', 'en': 'Evaluate '},
    'hr_eval_form_already_evaluated': {
      'ar': 'تم تقييم هذا الموظف مسبقاً لهذه الفترة',
      'en': 'This employee has already been evaluated for this period',
    },
    'hr_eval_form_no_kpis': {
      'ar': 'لا توجد مؤشرات أداء مطبّقة على وظيفة هذا الموظف',
      'en': 'No performance indicators apply to this employee\'s job',
    },
    'hr_eval_form_weight_prefix': {'ar': 'الوزن: ', 'en': 'Weight: '},

    // شاشة تفاصيل تقييم سابق (team_evaluation_detail_screen.dart)
    'hr_eval_detail_title': {
      'ar': 'تفاصيل التقييم',
      'en': 'Evaluation Details',
    },
    'hr_eval_detail_period_prefix': {'ar': 'الفترة: ', 'en': 'Period: '},
    'hr_eval_detail_total_score_prefix': {
      'ar': 'الدرجة الإجمالية: ',
      'en': 'Total Score: ',
    },
    'hr_eval_detail_status_finalized': {'ar': 'معتمد', 'en': 'Finalized'},
    'hr_eval_detail_status_draft': {'ar': 'مسودة', 'en': 'Draft'},
    'hr_eval_detail_notes_label': {'ar': 'ملاحظات عامة', 'en': 'General Notes'},
    'hr_eval_detail_items_title': {
      'ar': 'تفاصيل المؤشرات',
      'en': 'KPI Details',
    },
    'hr_eval_detail_load_error_prefix': {
      'ar': 'تعذر تحميل بيانات التقييم: ',
      'en': 'Failed to load evaluation data: ',
    },
    'hr_eval_form_notes_optional': {
      'ar': 'ملاحظات (اختياري)',
      'en': 'Notes (optional)',
    },
    'hr_eval_form_overall_notes_optional': {
      'ar': 'ملاحظات عامة على التقييم (اختياري)',
      'en': 'General notes on the evaluation (optional)',
    },
    'hr_eval_form_saving': {'ar': 'جاري الحفظ...', 'en': 'Saving...'},
    'hr_eval_form_save_button': {'ar': 'حفظ التقييم', 'en': 'Save Evaluation'},

    // تقييم أداء الفريق (المدير)
    'hr_eval_load_error_prefix': {
      'ar': 'تعذر تحميل بيانات الفريق: ',
      'en': 'Could not load team data: ',
    },
    'hr_eval_title': {
      'ar': 'تقييم أداء الفريق',
      'en': 'Team Performance Evaluation',
    },
    'hr_eval_no_subordinates': {
      'ar': 'لا يوجد مرؤوسون ضمن هيكلك الإداري',
      'en': 'No subordinates within your organizational structure',
    },
    'hr_eval_evaluated_prefix': {'ar': 'تم التقييم (', 'en': 'Evaluated ('},
    'hr_eval_evaluated_suffix': {'ar': ')', 'en': ')'},
    'nav_team_records': {'ar': 'الموظفون وسجلي الوظيفي', 'en': 'Team and employee records'},
    'hr_eval_view_button': {'ar': 'عرض التقييم', 'en': 'View evaluation'},
    'hr_eval_pending': {'ar': 'بانتظار التقييم', 'en': 'Pending evaluation'},

    // المبيعات: تبويب الطلبات (orders_tab.dart)
    'sales_orders_load_error': {
      'ar': 'تعذر تحميل الطلبات',
      'en': 'Could not load orders',
    },
    'sales_orders_update_error': {
      'ar': 'تعذر التحديث، تحقق من الاتصال',
      'en': 'Could not update, check your connection',
    },
    'sales_orders_pending_only': {'ar': 'معلق فقط', 'en': 'Pending only'},
    'sales_orders_all': {'ar': 'الكل', 'en': 'All'},
    'sales_orders_empty': {'ar': 'لا توجد طلبات', 'en': 'No orders'},
    'sales_orders_update_to_prefix': {'ar': 'تحديث إلى: ', 'en': 'Update to: '},

    // المبيعات: نقطة البيع (pos_tab.dart)
    'pos_load_error': {
      'ar': 'تعذر تحميل قائمة الطعام',
      'en': 'Could not load the menu',
    },
    'pos_checkout_success': {
      'ar': 'تم إنشاء الطلب بنجاح',
      'en': 'Order created successfully',
    },
    'pos_category_all': {'ar': 'الكل', 'en': 'All'},
    'pos_no_items_in_category': {
      'ar': 'لا توجد أصناف في هذا القسم',
      'en': 'No items in this category',
    },
    'pos_floor_label': {'ar': 'الطابق/الصالة', 'en': 'Floor/Hall'},
    'pos_floor_default_all': {
      'ar': 'الكل (المنيو الافتراضي)',
      'en': 'All (default menu)',
    },
    'pos_table_label': {'ar': 'الطاولة', 'en': 'Table'},
    'pos_no_table': {'ar': 'بدون طاولة', 'en': 'No table'},
    'pos_view_cart': {'ar': 'عرض السلة', 'en': 'View cart'},
    'pos_cart_title': {'ar': 'السلة', 'en': 'Cart'},
    'pos_cart_empty': {'ar': 'السلة فارغة', 'en': 'Cart is empty'},
    'pos_total': {'ar': 'الإجمالي', 'en': 'Total'},
    'pos_complete_sale': {'ar': 'إتمام البيع', 'en': 'Complete sale'},

    // المبيعات: شاشة المبيعات (sales_screen.dart)
    'sales_tab_orders': {'ar': 'الطلبات', 'en': 'Orders'},
    'sales_tab_pos': {'ar': 'نقطة البيع', 'en': 'Point of Sale'},

    // المبيعات: تفاصيل الطلب (order_details_screen.dart)
    'order_status_pending': {'ar': 'معلق', 'en': 'Pending'},
    'order_status_confirmed': {'ar': 'مؤكد', 'en': 'Confirmed'},
    'order_status_preparing': {'ar': 'قيد التحضير', 'en': 'Preparing'},
    'order_status_ready': {'ar': 'جاهز', 'en': 'Ready'},
    'order_status_served': {'ar': 'مقدم', 'en': 'Served'},
    'order_status_delivering': {'ar': 'قيد التوصيل', 'en': 'Delivering'},
    'order_status_completed': {'ar': 'مكتمل', 'en': 'Completed'},
    'order_status_cancelled': {'ar': 'ملغي', 'en': 'Cancelled'},
    'order_type_dine_in': {'ar': 'صالة', 'en': 'Dine-in'},
    'order_type_takeaway': {'ar': 'سفري', 'en': 'Takeaway'},
    'order_type_delivery': {'ar': 'توصيل', 'en': 'Delivery'},
    'order_type_online': {'ar': 'إلكتروني', 'en': 'Online'},
    'order_details_load_error': {
      'ar': 'تعذر تحميل الطلب',
      'en': 'Could not load the order',
    },
    'order_details_connection_error': {
      'ar': 'تعذر الاتصال بالخادم',
      'en': 'Could not connect to the server',
    },
    'order_details_action_failed': {
      'ar': 'فشل تنفيذ الإجراء',
      'en': 'Failed to perform the action',
    },
    'order_details_no_internet': {
      'ar': 'تعذر الاتصال، تحقق من الإنترنت',
      'en': 'Could not connect, check your internet',
    },
    'order_details_approved': {
      'ar': 'تمت الموافقة على الطلب',
      'en': 'Order approved',
    },
    'order_details_rejected': {'ar': 'تم رفض الطلب', 'en': 'Order rejected'},
    'order_details_edited': {'ar': 'تم تعديل الطلب', 'en': 'Order edited'},
    'order_details_updated': {'ar': 'تم التحديث', 'en': 'Updated'},
    'order_details_title': {'ar': 'تفاصيل الطلب', 'en': 'Order Details'},
    'order_details_order_prefix': {'ar': 'طلب ', 'en': 'Order '},
    'order_details_no_data': {'ar': 'لا توجد بيانات', 'en': 'No data'},
    'order_details_table_prefix': {'ar': 'طاولة ', 'en': 'Table '},
    'order_details_no_table': {'ar': 'بدون طاولة', 'en': 'No table'},
    'order_details_item_fallback': {'ar': 'صنف', 'en': 'Item'},
    'order_details_notes_label': {
      'ar': 'ملاحظات المدير',
      'en': 'Manager Notes',
    },
    'order_details_notes_hint': {
      'ar': 'سبب الرفض أو تعليمات التعديل...',
      'en': 'Rejection reason or edit instructions...',
    },
    'order_details_no_actions': {
      'ar': 'لا يمكن اتخاذ إجراءات على هذا الطلب',
      'en': 'No actions can be taken on this order',
    },
    'order_details_save_edits': {'ar': 'حفظ التعديلات', 'en': 'Save Edits'},
    'order_details_approve': {'ar': 'موافقة', 'en': 'Approve'},
    'order_details_reject': {'ar': 'رفض', 'en': 'Reject'},
    'order_details_edit_items': {'ar': 'تعديل الأصناف', 'en': 'Edit Items'},

    // الكابتن: سلة الطلب (captain_order_cart.dart)
    'captain_cart_order_table_prefix': {
      'ar': 'طلب طاولة ',
      'en': 'Order for table ',
    },
    'captain_cart_back': {'ar': 'رجوع', 'en': 'Back'},
    'captain_cart_title': {'ar': '🛒 السلة', 'en': '🛒 Cart'},
    'captain_cart_empty': {'ar': 'السلة فارغة', 'en': 'Cart is empty'},
    'captain_cart_total_label': {'ar': 'إجمالي السلة:', 'en': 'Cart Total:'},
    'captain_cart_notes_label': {'ar': 'ملاحظات', 'en': 'Notes'},
    'captain_cart_add_to_order': {'ar': 'إضافة للطلب', 'en': 'Add to Order'},
    'captain_cart_send_order': {'ar': 'إرسال الطلب', 'en': 'Send Order'},
    'captain_cart_open_order_prefix': {
      'ar': 'طلب مفتوح: ',
      'en': 'Open order: ',
    },
    'captain_cart_current_total': {
      'ar': 'المجموع الحالي',
      'en': 'Current Total',
    },

    // الكابتن: منطق التحكم بالطلب (captain_order_controller.dart)
    'captain_controller_load_error': {
      'ar': 'تعذر تحميل قائمة الطعام',
      'en': 'Could not load the menu',
    },
    'captain_controller_send_failed': {
      'ar': 'فشل في الإرسال',
      'en': 'Failed to send',
    },
    'captain_controller_queued_offline': {
      'ar': 'لا يوجد اتصال، سيتم الإرسال لاحقاً',
      'en': 'No connection, will be sent later',
    },
    'captain_controller_items_added': {
      'ar': 'تم إضافة الأصناف للطلب',
      'en': 'Items added to the order',
    },
    'captain_controller_order_sent': {
      'ar': 'تم إرسال الطلب',
      'en': 'Order sent',
    },

    // الكابتن: شاشة الطلب والعناصر المشتركة
    'captain_order_title_prefix': {'ar': 'طلب ', 'en': 'Order '},
    'captain_category_all': {'ar': 'الكل', 'en': 'All'},
    'captain_items_empty_category': {
      'ar': 'لا توجد أصناف في هذا القسم',
      'en': 'No items in this category',
    },

    // الكابتن: الشاشة الرئيسية (captain_screen.dart)
    'captain_screen_title': {'ar': 'شاشة الكابتن', 'en': 'Captain Screen'},
    'captain_logout_tooltip': {'ar': 'تسجيل الخروج', 'en': 'Logout'},

    // الكابتن: شاشة الطاولات (captain_tables_screen.dart)
    'captain_tables_load_error': {
      'ar': 'تعذر تحميل الطاولات',
      'en': 'Could not load tables',
    },
    'captain_tables_chair_suffix': {'ar': ' كرسي', 'en': ' chairs'},
    'captain_tables_order_prefix': {'ar': 'طلب: ', 'en': 'Order: '},

    // المطبخ (kitchen_screen.dart)
    'kitchen_load_error': {
      'ar': 'تعذر تحميل شاشة المطبخ',
      'en': 'Could not load the kitchen screen',
    },
    'kitchen_update_error': {
      'ar': 'تعذر التحديث، تحقق من الاتصال',
      'en': 'Could not update, check your connection',
    },
    'kitchen_no_active_orders': {
      'ar': 'لا توجد طلبات نشطة',
      'en': 'No active orders',
    },
    'kitchen_minutes_suffix': {'ar': ' دقيقة', 'en': ' min'},
    'kitchen_status_preparing': {'ar': 'قيد التحضير', 'en': 'Preparing'},
    'kitchen_status_ready': {'ar': 'جاهز', 'en': 'Ready'},
    'kitchen_status_served': {'ar': 'تم التقديم', 'en': 'Served'},

    // كول سنتر (call_center_screen.dart)
    'cc_load_types_error': {
      'ar': 'تعذر تحميل أنواع الحجز',
      'en': 'Could not load reservation types',
    },
    'cc_search_error': {'ar': 'تعذر البحث', 'en': 'Search failed'},
    'cc_customer_load_error': {
      'ar': 'تعذر تحميل بيانات العميل',
      'en': 'Could not load customer data',
    },
    'cc_name_required': {
      'ar': 'اسم العميل مطلوب',
      'en': 'Customer name is required',
    },
    'cc_date_time_required': {
      'ar': 'التاريخ والوقت مطلوبان',
      'en': 'Date and time are required',
    },
    'cc_save_success': {'ar': 'تم حفظ الحجز', 'en': 'Reservation saved'},
    'cc_save_failed': {'ar': 'فشل الحفظ', 'en': 'Save failed'},
    'cc_save_error': {
      'ar': 'تعذر حفظ الحجز',
      'en': 'Could not save the reservation',
    },
    'cc_search_hint': {
      'ar': 'البحث بالاسم أو الهاتف',
      'en': 'Search by name or phone',
    },
    'cc_form_name_label': {'ar': 'اسم العميل', 'en': 'Customer Name'},
    'cc_phone_label': {'ar': 'الهاتف', 'en': 'Phone'},
    'cc_type_label': {'ar': 'نوع الحجز', 'en': 'Reservation Type'},
    'cc_guests_label': {'ar': 'الضيوف', 'en': 'Guests'},
    'cc_duration_label': {'ar': 'المدة (د)', 'en': 'Duration (min)'},
    'cc_date_label': {'ar': 'التاريخ', 'en': 'Date'},
    'cc_time_label': {'ar': 'الوقت', 'en': 'Time'},
    'cc_notes_label': {'ar': 'ملاحظات', 'en': 'Notes'},
    'cc_cc_notes_label': {
      'ar': 'ملاحظات الكول سنتر',
      'en': 'Call Center Notes',
    },
    'cc_history_title': {'ar': 'تاريخ الحجوزات', 'en': 'Reservation History'},
    'cc_guests_suffix': {'ar': ' ضيوف | ', 'en': ' guests | '},
    'cc_title': {'ar': 'كول سنتر', 'en': 'Call Center'},
    'cc_customer_prefix': {'ar': 'العميل: ', 'en': 'Customer: '},
    'cc_occupancy_prefix': {'ar': 'الإشغال: ', 'en': 'Occupancy: '},
    'cc_save_button': {'ar': 'حفظ الحجز', 'en': 'Save Reservation'},
    'cc_new_button': {'ar': 'جديد', 'en': 'New'},

    // الحجوزات (reservations_screen.dart)
    'reservations_load_error': {
      'ar': 'تعذر تحميل الحجوزات',
      'en': 'Could not load reservations',
    },
    'reservations_new_title': {'ar': 'حجز جديد', 'en': 'New Reservation'},
    'reservations_name_label': {'ar': 'اسم العميل', 'en': 'Customer Name'},
    'reservations_phone_label': {'ar': 'رقم الهاتف', 'en': 'Phone Number'},
    'reservations_guests_label': {'ar': 'عدد الضيوف', 'en': 'Number of Guests'},
    'reservations_datetime_label': {
      'ar': 'الموعد (YYYY-MM-DD HH:MM)',
      'en': 'Date & Time (YYYY-MM-DD HH:MM)',
    },
    'reservations_create_success': {
      'ar': 'تم إنشاء الحجز',
      'en': 'Reservation created',
    },
    'reservations_empty': {'ar': 'لا توجد حجوزات', 'en': 'No reservations'},
    'reservations_guests_suffix': {'ar': ' أشخاص', 'en': ' people'},
    'reservations_no_table': {'ar': 'بدون طاولة', 'en': 'No table'},

    // المخزون: مسح الباركود (barcode_scan_screen.dart)
    'inventory_barcode_scan_title': {
      'ar': 'مسح الباركود',
      'en': 'Barcode Scan',
    },
    'inventory_barcode_scan_manual_hint': {
      'ar': 'أو أدخل الباركود يدوياً',
      'en': 'Or enter the barcode manually',
    },
    'inventory_barcode_scan_item_not_found': {
      'ar': 'صنف غير موجود',
      'en': 'Item not found',
    },
    'inventory_barcode_scan_search_error': {
      'ar': 'تعذّر البحث عن الباركود',
      'en': 'Could not search for the barcode',
    },
    'inventory_barcode_scan_empty_hint': {
      'ar': 'امسح باركود لعرض تفاصيل الصنف',
      'en': 'Scan a barcode to view item details',
    },
    'inventory_barcode_scan_type_label': {'ar': 'النوع: ', 'en': 'Type: '},
    'inventory_barcode_scan_unit_label': {'ar': 'الوحدة: ', 'en': 'Unit: '},
    'inventory_barcode_scan_stock_label': {'ar': 'المخزون: ', 'en': 'Stock: '},
    'inventory_barcode_scan_total_label': {'ar': 'إجمالي: ', 'en': 'Total: '},
    'inventory_barcode_scan_barcode_label': {
      'ar': 'الباركود: ',
      'en': 'Barcode: ',
    },
    'inventory_barcode_scan_action_in': {'ar': 'إدخال', 'en': 'Stock In'},
    'inventory_barcode_scan_action_out': {'ar': 'إخراج', 'en': 'Stock Out'},
    'inventory_barcode_scan_action_transfer': {'ar': 'تحويل', 'en': 'Transfer'},
    'inventory_barcode_scan_action_custody': {
      'ar': 'تسليم عهدة',
      'en': 'Issue Custody',
    },

    // المخزون: الشاشة الرئيسية (inventory_screen.dart)
    'inventory_screen_load_error': {
      'ar': 'تعذر تحميل بيانات المخزون',
      'en': 'Could not load inventory data',
    },
    'inventory_screen_empty': {'ar': 'لا توجد أصناف', 'en': 'No items found'},

    // المخزون: التصنيع (manufacture_screen.dart)
    'inventory_manufacture_title': {'ar': 'تصنيع', 'en': 'Manufacturing'},
    'inventory_manufacture_recipe_label': {'ar': 'الوصفة', 'en': 'Recipe'},
    'inventory_manufacture_qty_label': {
      'ar': 'كمية المنتج',
      'en': 'Product Quantity',
    },
    'inventory_manufacture_from_warehouse_label': {
      'ar': 'مخزن المكوّنات',
      'en': 'Components Warehouse',
    },
    'inventory_manufacture_to_warehouse_label': {
      'ar': 'مخزن الناتج',
      'en': 'Output Warehouse',
    },
    'inventory_manufacture_success': {
      'ar': 'تم تسجيل التصنيع',
      'en': 'Manufacturing recorded successfully',
    },
    'inventory_manufacture_failed': {
      'ar': 'فشل التصنيع',
      'en': 'Manufacturing failed',
    },
    'inventory_manufacture_submit_error': {
      'ar': 'تعذّر الإرسال',
      'en': 'Could not submit',
    },
    'inventory_manufacture_submit_button': {
      'ar': 'تسجيل التصنيع',
      'en': 'Record Manufacturing',
    },

    // المخزون: تقارير المخزون (inventory/reports_screen.dart)
    'inventory_reports_title': {
      'ar': 'تقرير المخزون',
      'en': 'Inventory Report',
    },
    'inventory_reports_type_label': {'ar': 'نوع الصنف', 'en': 'Item Type'},
    'inventory_reports_view_button': {'ar': 'عرض', 'en': 'View'},
    'inventory_reports_load_failed': {
      'ar': 'فشل تحميل التقرير',
      'en': 'Failed to load the report',
    },
    'inventory_reports_load_error': {
      'ar': 'تعذّر تحميل التقرير',
      'en': 'Could not load the report',
    },
    'inventory_reports_empty': {
      'ar': 'لا يوجد بيانات',
      'en': 'No data available',
    },
    'inventory_reports_items_label': {'ar': 'الأصناف: ', 'en': 'Items: '},
    'inventory_reports_qty_label': {
      'ar': ' • إجمالي الكمية: ',
      'en': ' • Total Qty: ',
    },
    'inventory_reports_type_all': {'ar': 'الكل', 'en': 'All'},
    'inventory_reports_type_raw': {'ar': 'مواد خام', 'en': 'Raw Materials'},
    'inventory_reports_type_asset': {'ar': 'أصول', 'en': 'Assets'},
    'inventory_reports_type_tool': {'ar': 'أدوات', 'en': 'Tools'},
    'inventory_reports_type_clothing': {'ar': 'ملابس', 'en': 'Clothing'},
    'inventory_reports_type_prepared': {'ar': 'جاهز', 'en': 'Prepared'},

    // المخزون: إدخال/إخراج مخزوني (stock_entry_screen.dart / stock_exit_screen.dart)
    'inventory_stock_entry_title': {'ar': 'إدخال مخزوني', 'en': 'Stock Entry'},
    'inventory_stock_exit_title': {'ar': 'إخراج مخزوني', 'en': 'Stock Exit'},

    // المخزون: حركة المخزون (stock_movement_screen.dart)
    'inventory_stock_movement_item_not_found': {
      'ar': 'صنف غير موجود',
      'en': 'Item not found',
    },
    'inventory_stock_movement_search_error': {
      'ar': 'تعذّر البحث',
      'en': 'Search failed',
    },
    'inventory_stock_movement_success_prefix': {
      'ar': 'تم تسجيل الحركة. الرصيد: ',
      'en': 'Movement recorded. Balance: ',
    },
    'inventory_stock_movement_failed': {
      'ar': 'فشل تسجيل',
      'en': 'Recording failed',
    },
    'inventory_stock_movement_submit_error': {
      'ar': 'تعذّر الإرسال',
      'en': 'Could not submit',
    },
    'inventory_stock_movement_warehouse_label': {
      'ar': 'المخزن',
      'en': 'Warehouse',
    },
    'inventory_stock_movement_qty_label': {'ar': 'الكمية', 'en': 'Quantity'},
    'inventory_stock_movement_notes_label': {'ar': 'ملاحظات', 'en': 'Notes'},
    'inventory_stock_movement_submit_in': {
      'ar': 'تسجيل إدخال',
      'en': 'Record Entry',
    },
    'inventory_stock_movement_submit_out': {
      'ar': 'تسجيل إخراج',
      'en': 'Record Exit',
    },
    'inventory_stock_movement_barcode_label': {
      'ar': 'باركود الصنف',
      'en': 'Item Barcode',
    },

    // المخزون: الجرد (stocktake_screen.dart)
    'inventory_stocktake_item_not_found': {
      'ar': 'صنف غير موجود',
      'en': 'Item not found',
    },
    'inventory_stocktake_search_error': {
      'ar': 'تعذّر البحث',
      'en': 'Search failed',
    },
    'inventory_stocktake_success_prefix': {
      'ar': 'تم تسجيل الجرد. الفروقات: ',
      'en': 'Stocktake recorded. Differences: ',
    },
    'inventory_stocktake_failed': {
      'ar': 'فشل تسجيل الجرد',
      'en': 'Failed to record the stocktake',
    },
    'inventory_stocktake_submit_error': {
      'ar': 'تعذّر الإرسال',
      'en': 'Could not submit',
    },
    'inventory_stocktake_title': {
      'ar': 'جرد المخزون',
      'en': 'Inventory Stocktake',
    },
    'inventory_stocktake_warehouse_label': {'ar': 'المخزن', 'en': 'Warehouse'},
    'inventory_stocktake_barcode_label': {
      'ar': 'باركود الصنف',
      'en': 'Item Barcode',
    },
    'inventory_stocktake_qty_label': {'ar': 'عدد', 'en': 'Qty'},
    'inventory_stocktake_save_button': {
      'ar': 'حفظ الجرد',
      'en': 'Save Stocktake',
    },

    // المخزون: التحويل (transfer_screen.dart)
    'inventory_transfer_item_not_found': {
      'ar': 'صنف غير موجود',
      'en': 'Item not found',
    },
    'inventory_transfer_search_error': {
      'ar': 'تعذّر البحث',
      'en': 'Search failed',
    },
    'inventory_transfer_success_prefix': {
      'ar': 'تم إنشاء التحويل ',
      'en': 'Transfer created ',
    },
    'inventory_transfer_failed': {'ar': 'فشل التحويل', 'en': 'Transfer failed'},
    'inventory_transfer_submit_error': {
      'ar': 'تعذّر الإرسال',
      'en': 'Could not submit',
    },
    'inventory_transfer_title': {'ar': 'تحويل مخزوني', 'en': 'Stock Transfer'},
    'inventory_transfer_from_label': {'ar': 'من مخزن', 'en': 'From Warehouse'},
    'inventory_transfer_to_label': {'ar': 'إلى مخزن', 'en': 'To Warehouse'},
    'inventory_transfer_barcode_label': {
      'ar': 'باركود الصنف',
      'en': 'Item Barcode',
    },
    'inventory_transfer_qty_label': {'ar': 'كمية', 'en': 'Quantity'},
    'inventory_transfer_notes_label': {'ar': 'ملاحظات', 'en': 'Notes'},
    'inventory_transfer_submit_button': {
      'ar': 'إنشاء التحويل',
      'en': 'Create Transfer',
    },

    // المشتريات (purchasing/purchasing_screen.dart)
    'purchasing_load_error': {
      'ar': 'تعذر تحميل بيانات المشتريات',
      'en': 'Could not load purchasing data',
    },
    'purchasing_tab_requests': {
      'ar': 'طلبات الشراء',
      'en': 'Purchase Requests',
    },
    'purchasing_tab_orders': {'ar': 'أوامر الشراء', 'en': 'Purchase Orders'},
    'purchasing_request_status_label': {'ar': 'الحالة: ', 'en': 'Status: '},
    'purchasing_request_requester_label': {
      'ar': ' • الطالب: ',
      'en': ' • Requester: ',
    },
    'purchasing_order_status_label': {'ar': 'الحالة: ', 'en': 'Status: '},
    'purchasing_order_total_label': {'ar': ' • الإجمالي: ', 'en': ' • Total: '},
    'purchasing_empty': {'ar': 'لا توجد بيانات', 'en': 'No data available'},

    // التقارير (reports/reports_screen.dart)
    'reports_load_error': {
      'ar': 'تعذر تحميل التقارير',
      'en': 'Could not load reports',
    },
    'reports_sales_title': {
      'ar': 'تقرير المبيعات (الشهر الحالي)',
      'en': 'Sales Report (Current Month)',
    },
    'reports_orders_count': {'ar': 'عدد الطلبات', 'en': 'Orders Count'},
    'reports_total_sales': {'ar': 'إجمالي المبيعات', 'en': 'Total Sales'},
    'reports_avg_order': {'ar': 'متوسط الطلب', 'en': 'Average Order'},
    'reports_profit_loss_title': {
      'ar': 'الأرباح والخسائر',
      'en': 'Profit & Loss',
    },
    'reports_sales_label': {'ar': 'المبيعات', 'en': 'Sales'},
    'reports_cogs_label': {'ar': 'تكلفة البضاعة', 'en': 'Cost of Goods'},
    'reports_expenses_label': {'ar': 'المصروفات', 'en': 'Expenses'},

    // الموافقات: الشاشة الرئيسية (approvals_screen.dart)
    'approvals_title': {'ar': 'الموافقات', 'en': 'Approvals'},
    'approvals_create_tooltip': {'ar': 'إنشاء طلب', 'en': 'Create Request'},
    'approvals_choose_type_tooltip': {
      'ar': 'اختر نوع الطلب',
      'en': 'Choose Request Type',
    },
    'approvals_load_error': {
      'ar': 'تعذر تحميل الموافقات',
      'en': 'Could not load approvals',
    },
    'approvals_connection_error_prefix': {
      'ar': 'تعذر الاتصال بالخادم (',
      'en': 'Could not connect to the server (',
    },
    'approvals_decide_error': {
      'ar': 'تعذر إرسال القرار، تحقق من الاتصال',
      'en': 'Could not send the decision, check your connection',
    },
    'approvals_cancel_error': {
      'ar': 'تعذر إلغاء الطلب',
      'en': 'Could not cancel the request',
    },
    'approvals_empty': {
      'ar': 'لا توجد طلبات حالياً',
      'en': 'No requests currently',
    },
    'approvals_status_draft': {'ar': 'مسوّدة', 'en': 'Draft'},
    'approvals_status_pending': {'ar': 'معلق', 'en': 'Pending'},
    'approvals_status_approved': {'ar': 'معتمد', 'en': 'Approved'},
    'approvals_status_rejected': {'ar': 'مرفوض', 'en': 'Rejected'},
    'approvals_status_returned_for_edit': {
      'ar': 'يعيد تعديل',
      'en': 'Returned for edit',
    },
    'approvals_status_cancelled': {'ar': 'ملغى', 'en': 'Cancelled'},
    'approvals_maint_status_pending': {'ar': 'معلق', 'en': 'Pending'},
    'approvals_maint_status_assigned': {'ar': 'معين', 'en': 'Assigned'},
    'approvals_maint_status_in_progress': {
      'ar': 'قيد التنفيذ',
      'en': 'In Progress',
    },
    'approvals_maint_status_completed': {'ar': 'منتهي', 'en': 'Completed'},
    'approvals_maint_status_confirmed': {'ar': 'تم التأكيد', 'en': 'Confirmed'},
    'approvals_maint_status_cancelled': {'ar': 'ملغى', 'en': 'Cancelled'},
    'approvals_maintenance_fallback_title': {
      'ar': 'طلب صيانة',
      'en': 'Maintenance Request',
    },
    'approvals_requester_label': {'ar': 'مقدم الطلب: ', 'en': 'Requester: '},
    'approvals_assigned_label': {'ar': ' — المعين: ', 'en': ' — Assigned: '},
    'approvals_request_fallback_title': {
      'ar': 'طلب موافقة',
      'en': 'Approval Request',
    },
    'approvals_from_label': {'ar': 'من: ', 'en': 'From: '},
    'approvals_step_label': {'ar': ' — الخطوة: ', 'en': ' — Step: '},
    'approvals_amount_label': {'ar': ' — المبلغ: ', 'en': ' — Amount: '},
    'approvals_manager_note_label': {
      'ar': 'ملاحظة المدير: ',
      'en': 'Manager note: ',
    },
    'approvals_edit_button': {'ar': 'تعديل', 'en': 'Edit'},
    'approvals_cancel_button': {'ar': 'إلغاء', 'en': 'Cancel'},
    'approvals_cancel_dialog_title': {
      'ar': 'إلغاء الطلب',
      'en': 'Cancel Request',
    },
    'approvals_cancel_dialog_content': {
      'ar': 'هل أنت متأكد من إلغاء هذا الطلب؟',
      'en': 'Are you sure you want to cancel this request?',
    },
    'approvals_dialog_back': {'ar': 'تراجع', 'en': 'Back'},
    'approvals_menu_shift_assign': {
      'ar': 'تعيين وردية للموظفين',
      'en': 'Assign shift to employees',
    },
    'approvals_menu_overtime': {
      'ar': 'طلب عمل إضافي',
      'en': 'Overtime request',
    },
    'approvals_template_fallback': {'ar': 'طلب', 'en': 'Request'},

    // الأرشيف (archive_screen.dart)
    'archive_title': {'ar': 'الأرشيف', 'en': 'Archive'},
    'archive_load_error': {
      'ar': 'تعذر تحميل الأرشيف',
      'en': 'Could not load the archive',
    },
    'archive_empty': {
      'ar': 'لا توجد طلبات مؤرشفة حتى الآن',
      'en': 'No archived requests yet',
    },
    'archive_status_approved': {'ar': 'معتمد', 'en': 'Approved'},
    'archive_status_rejected': {'ar': 'مرفوض', 'en': 'Rejected'},
    'archive_maint_status_confirmed': {'ar': 'تم التأكيد', 'en': 'Confirmed'},
    'archive_maint_status_cancelled': {'ar': 'ملغى', 'en': 'Cancelled'},
    'archive_maintenance_fallback_title': {
      'ar': 'طلب صيانة',
      'en': 'Maintenance Request',
    },
    'archive_requester_label': {'ar': 'مقدم الطلب: ', 'en': 'Requester: '},
    'archive_request_fallback_title': {
      'ar': 'طلب موافقة',
      'en': 'Approval Request',
    },
    'archive_number_label': {'ar': 'رقم: ', 'en': 'No.: '},
    'archive_amount_label': {'ar': ' — المبلغ: ', 'en': ' — Amount: '},

    // إنشاء طلب موافقة (workflow_create_screen.dart)
    'workflow_create_templates_load_error_prefix': {
      'ar': 'تعذر تحميل قوالب الموافقات: ',
      'en': 'Could not load approval templates: ',
    },
    'workflow_create_template_details_error_prefix': {
      'ar': 'تعذر تحميل تفاصيل القالب: ',
      'en': 'Could not load template details: ',
    },
    'workflow_create_items_load_error': {
      'ar': 'تعذر تحميل الأصناف',
      'en': 'Could not load items',
    },
    'workflow_create_add_item': {'ar': 'إضافة صنف', 'en': 'Add Item'},
    'workflow_create_item_required': {
      'ar': 'يجب إضافة صنف واحد على الأقل بكمية صحيحة',
      'en': 'At least one item with a valid quantity must be added',
    },
    'workflow_create_item_name_label': {'ar': 'اسم الصنف', 'en': 'Item Name'},
    'workflow_create_qty_label': {'ar': 'الكمية', 'en': 'Quantity'},
    'workflow_create_reason_label': {'ar': 'السبب', 'en': 'Reason'},
    'workflow_create_short_unpaid_leave_limit': {
      'ar': 'الإجازة القصيرة بدون راتب يجب ألا تتجاوز مدتها 3 أيام',
      'en': 'Short unpaid leave must not exceed 3 days',
    },
    'workflow_create_long_unpaid_leave_limit': {
      'ar': 'الإجازة الطويلة بدون راتب يجب أن تكون مدتها 4 أيام فأكثر',
      'en': 'Long unpaid leave must be 4 days or more',
    },
    'workflow_create_default_hiring_title': {
      'ar': 'طلب تعيين',
      'en': 'Hiring Request',
    },
    'workflow_create_default_suspension_title': {
      'ar': 'طلب إيقاف دوام',
      'en': 'Suspension Request',
    },
    'workflow_create_default_approval_title': {
      'ar': 'طلب موافقة',
      'en': 'Approval Request',
    },
    'workflow_create_submitted_prefix': {
      'ar': 'تم إرسال الطلب: ',
      'en': 'Request sent: ',
    },
    'workflow_create_submit_failed': {
      'ar': 'فشل إرسال الطلب',
      'en': 'Failed to send request',
    },
    'workflow_create_submit_error': {
      'ar': 'تعذر إرسال الطلب، تحقق من الاتصال',
      'en': 'Could not send the request, check your connection',
    },
    'workflow_create_employee_label': {'ar': 'الموظف *', 'en': 'Employee *'},
    'workflow_create_department_label': {'ar': 'القسم', 'en': 'Department'},
    'workflow_create_no_file_chosen': {
      'ar': 'لم يُختار ملف',
      'en': 'No file chosen',
    },
    'workflow_create_date_label': {'ar': 'التاريخ', 'en': 'Date'},
    'workflow_create_choose_prefix': {'ar': 'اختر ', 'en': 'Choose '},
    'workflow_create_search_by_name': {
      'ar': 'ابحث بالاسم...',
      'en': 'Search by name...',
    },
    'workflow_create_choose_date': {'ar': 'اختر التاريخ', 'en': 'Select Date'},
    'workflow_create_start_time_label': {'ar': 'من الساعة', 'en': 'Start Time'},
    'workflow_create_end_time_label': {'ar': 'إلى الساعة', 'en': 'End Time'},
    'workflow_create_start_time_required': {
      'ar': 'وقت البداية مطلوب',
      'en': 'Start time is required',
    },
    'workflow_create_end_time_required': {
      'ar': 'وقت النهاية مطلوب',
      'en': 'End time is required',
    },
    'workflow_create_end_after_start': {
      'ar': 'وقت النهاية يجب أن يكون بعد وقت البداية',
      'en': 'End time must be after start time',
    },
    'workflow_create_choose_time_range': {
      'ar': 'اختر نطاق الوقت',
      'en': 'Select Time Range',
    },
    'workflow_create_from_date_label': {'ar': 'من تاريخ', 'en': 'From Date'},
    'workflow_create_to_date_label': {'ar': 'إلى تاريخ', 'en': 'To Date'},
    'workflow_create_start_date_required': {
      'ar': 'تاريخ البداية مطلوب',
      'en': 'Start date is required',
    },
    'workflow_create_end_date_required': {
      'ar': 'تاريخ النهاية مطلوب',
      'en': 'End date is required',
    },
    'workflow_create_choose_date_range': {
      'ar': 'اختر نطاق التاريخ',
      'en': 'Select Date Range',
    },
    'workflow_create_screen_title': {
      'ar': 'إنشاء طلب موافقة',
      'en': 'Create Approval Request',
    },
    'workflow_create_access_denied': {
      'ar': 'ليس لديك صلاحية إنشاء طلب موافقة',
      'en': 'You do not have permission to create an approval request',
    },
    'workflow_create_choose_type_title': {
      'ar': 'اختيار نوع الطلب',
      'en': 'Choose Request Type',
    },
    'workflow_create_title_prefix': {'ar': 'إنشاء ', 'en': 'Create '},
    'workflow_create_no_options': {
      'ar': 'لا توجد خيارات متاحة لهذا الحساب',
      'en': 'No options available for this account',
    },
    'workflow_create_maintenance_section': {
      'ar': 'طلبات الصيانة',
      'en': 'Maintenance Requests',
    },
    'workflow_create_maintenance_item_title': {
      'ar': 'طلب صيانة',
      'en': 'Maintenance Request',
    },
    'workflow_create_maintenance_item_subtitle': {
      'ar': 'إبلاغ عن عطل في جهاز أو معدات',
      'en': 'Report a fault in a device or equipment',
    },
    'workflow_create_manager_section': {
      'ar': 'طلبات المدير',
      'en': 'Manager Requests',
    },
    'workflow_create_shift_assign_title': {
      'ar': 'تعيين وردية للموظفين',
      'en': 'Assign Shift to Employees',
    },
    'workflow_create_shift_assign_subtitle': {
      'ar': 'تعيين وردية محددة لموظف',
      'en': 'Assign a specific shift to an employee',
    },
    'workflow_create_overtime_section': {
      'ar': 'العمل الإضافي',
      'en': 'Overtime',
    },
    'workflow_create_overtime_item_title': {
      'ar': 'طلب عمل إضافي',
      'en': 'Overtime Request',
    },
    'workflow_create_overtime_item_subtitle': {
      'ar': 'تقديم طلب عمل إضافي جديد',
      'en': 'Submit a new overtime request',
    },
    'workflow_create_approvals_section': {
      'ar': 'طلبات الموافقات',
      'en': 'Approval Requests',
    },
    'workflow_create_no_templates': {
      'ar': 'لا توجد قوالب متاحة لهذا الحساب',
      'en': 'No templates available for this account',
    },
    'workflow_create_unnamed_template': {
      'ar': 'قالب بدون اسم',
      'en': 'Unnamed Template',
    },
    'workflow_create_required_field': {'ar': 'مطلوب', 'en': 'Required'},
    'workflow_create_the_employee_label': {'ar': 'الموظف', 'en': 'Employee'},
    'workflow_create_request_type_label': {
      'ar': 'نوع الطلب *',
      'en': 'Request Type *',
    },
    'workflow_create_daily_leave': {'ar': 'إجازة يومية', 'en': 'Daily Leave'},
    'workflow_create_hourly_leave': {'ar': 'إجازة زمنية', 'en': 'Hourly Leave'},
    'workflow_create_work_resumption': {
      'ar': 'مباشرة عمل',
      'en': 'Work Resumption',
    },
    'workflow_create_daily_leave_subtype_label': {
      'ar': 'نوع الإجازة اليومية *',
      'en': 'Daily Leave Subtype *',
    },
    'workflow_create_unpaid_short': {
      'ar': 'إجازة قصيرة بدون راتب (حتى 3 أيام)',
      'en': 'Short unpaid leave (up to 3 days)',
    },
    'workflow_create_unpaid_long': {
      'ar': 'إجازة طويلة بدون راتب (4 أيام فأكثر)',
      'en': 'Long unpaid leave (4 days or more)',
    },
    'workflow_create_no_employees_available': {
      'ar': 'لا يوجد موظفين متاحين',
      'en': 'No employees available',
    },
    'workflow_create_sending': {'ar': 'جاري الإرسال...', 'en': 'Sending...'},
    'workflow_create_submit_button': {
      'ar': 'إرسال الطلب',
      'en': 'Submit Request',
    },
    'workflow_create_missing_attendance_type_label': {
      'ar': 'نسيان',
      'en': 'Missing Record',
    },
    'workflow_create_missing_attendance_reason_label': {
      'ar': 'السبب / الملاحظات',
      'en': 'Reason / Notes',
    },
    'workflow_create_clock_in_type': {'ar': 'بصمة دخول', 'en': 'Clock In'},
    'workflow_create_clock_out_type': {'ar': 'بصمة خروج', 'en': 'Clock Out'},
    'workflow_create_datetime_label': {
      'ar': 'التاريخ والوقت *',
      'en': 'Date & Time *',
    },
    'workflow_create_datetime_required': {
      'ar': 'التاريخ والوقت مطلوب',
      'en': 'Date and time are required',
    },
    'workflow_create_date_required': {
      'ar': 'التاريخ مطلوب',
      'en': 'Date is required',
    },
    'workflow_create_salary_load_error': {
      'ar': 'لا يمكن تحميل بيانات الراتب',
      'en': 'Could not load salary data',
    },
    'workflow_create_advance_exhausted_month': {
      'ar': 'تم استنفاد حد السلفة المتاح لهذا الشهر',
      'en': 'The available advance limit for this month has been used up',
    },

    // تفاصيل طلب الموافقة (workflow_details_screen.dart)
    'workflow_details_status_draft': {'ar': 'مسوّدة', 'en': 'Draft'},
    'workflow_details_status_pending': {'ar': 'معلق', 'en': 'Pending'},
    'workflow_details_status_approved': {'ar': 'معتمد', 'en': 'Approved'},
    'workflow_details_status_rejected': {'ar': 'مرفوض', 'en': 'Rejected'},
    'workflow_details_status_returned_for_edit': {
      'ar': 'معاد للتعديل',
      'en': 'Returned for edit',
    },
    'workflow_details_status_cancelled': {'ar': 'ملغى', 'en': 'Cancelled'},
    'workflow_details_action_submitted': {'ar': 'إرسال', 'en': 'Submitted'},
    'workflow_details_action_approved': {'ar': 'موافقة', 'en': 'Approved'},
    'workflow_details_action_rejected': {'ar': 'رفض', 'en': 'Rejected'},
    'workflow_details_action_returned_for_edit': {
      'ar': 'إعادة للتعديل',
      'en': 'Returned for edit',
    },
    'workflow_details_action_resubmitted': {
      'ar': 'إعادة إرسال',
      'en': 'Resubmitted',
    },
    'workflow_details_action_cancelled': {'ar': 'إلغاء', 'en': 'Cancelled'},
    'workflow_details_action_edited': {'ar': 'تعديل', 'en': 'Edited'},
    'workflow_details_action_commented': {'ar': 'تعليق', 'en': 'Commented'},
    'workflow_details_action_delegated': {'ar': 'تفويض', 'en': 'Delegated'},
    'workflow_details_load_error': {
      'ar': 'تعذر تحميل تفاصيل الطلب',
      'en': 'Could not load request details',
    },
    'workflow_details_rejection_reason_required': {
      'ar': 'يرجى كتابة سبب الرفض في الملاحظات',
      'en': 'Please write the rejection reason in the notes',
    },
    'workflow_details_edit_instructions_required': {
      'ar': 'يرجى كتابة تعليمات التعديل في الملاحظات',
      'en': 'Please write the edit instructions in the notes',
    },
    'workflow_details_decision_failed': {
      'ar': 'فشل تسجيل القرار',
      'en': 'Failed to record the decision',
    },
    'workflow_details_decision_error': {
      'ar': 'تعذر إرسال القرار، تحقق من الاتصال',
      'en': 'Could not send the decision, check your connection',
    },
    'workflow_details_approved_message': {
      'ar': 'تمت الموافقة على الطلب',
      'en': 'Request approved',
    },
    'workflow_details_rejected_message': {
      'ar': 'تم رفض الطلب',
      'en': 'Request rejected',
    },
    'workflow_details_returned_message': {
      'ar': 'تمت إعادة الطلب للتعديل',
      'en': 'Request returned for edit',
    },
    'workflow_details_decision_recorded': {
      'ar': 'تم تسجيل القرار',
      'en': 'Decision recorded',
    },
    'workflow_details_title_fallback': {
      'ar': 'تفاصيل الطلب',
      'en': 'Request Details',
    },
    'workflow_details_request_no_label': {
      'ar': 'رقم الطلب',
      'en': 'Request No.',
    },
    'workflow_details_requester_label': {'ar': 'مقدم الطلب', 'en': 'Requester'},
    'workflow_details_job_title_label': {
      'ar': 'المسمى الوظيفي',
      'en': 'Job Title',
    },
    'workflow_details_department_label': {'ar': 'القسم', 'en': 'Department'},
    'workflow_details_position_label': {'ar': 'المنصب', 'en': 'Position'},
    'workflow_details_employee_label': {'ar': 'الموظف', 'en': 'Employee'},
    'workflow_details_amount_label': {'ar': 'المبلغ', 'en': 'Amount'},
    'workflow_details_created_at_label': {
      'ar': 'تاريخ الإنشاء',
      'en': 'Created At',
    },
    'workflow_details_data_section_title': {
      'ar': 'بيانات الطلب',
      'en': 'Request Data',
    },
    'workflow_details_items_column_item': {'ar': 'الصنف', 'en': 'Item'},
    'workflow_details_items_column_qty': {'ar': 'الكمية', 'en': 'Quantity'},
    'workflow_details_items_column_reason': {'ar': 'السبب', 'en': 'Reason'},
    'workflow_details_purchase_items_title': {
      'ar': 'الأصناف المطلوبة',
      'en': 'Requested Items',
    },
    'workflow_details_attachment_uploaded': {
      'ar': 'مرفق مرفوع',
      'en': 'Attachment uploaded',
    },
    'workflow_details_attachment_generic': {'ar': 'مرفق', 'en': 'Attachment'},
    'workflow_details_history_title': {
      'ar': 'سجل الإجراءات',
      'en': 'Action History',
    },
    'workflow_details_notes_label': {
      'ar': 'ملاحظات (سبب الرفض / تعليمات التعديل)',
      'en': 'Notes (rejection reason / edit instructions)',
    },
    'workflow_details_approve_button': {'ar': 'موافقة', 'en': 'Approve'},
    'workflow_details_reject_button': {'ar': 'رفض', 'en': 'Reject'},
    'workflow_details_return_for_edit_button': {
      'ar': 'إعادة للتعديل',
      'en': 'Return for Edit',
    },

    // تعديل طلب الموافقة (workflow_edit_screen.dart)
    'workflow_edit_load_failed': {
      'ar': 'فشل تحميل الطلب',
      'en': 'Failed to load the request',
    },
    'workflow_edit_load_error': {
      'ar': 'تعذر تحميل الطلب',
      'en': 'Could not load the request',
    },
    'workflow_edit_resubmitted_message': {
      'ar': 'تم إعادة إرسال الطلب',
      'en': 'Request resubmitted',
    },
    'workflow_edit_saved_message': {'ar': 'تم حفظ التعديل', 'en': 'Edit saved'},
    'workflow_edit_save_failed': {
      'ar': 'فشل حفظ التعديل',
      'en': 'Failed to save the edit',
    },
    'workflow_edit_save_error': {
      'ar': 'تعذر حفظ التعديل، تحقق من الاتصال',
      'en': 'Could not save the edit, check your connection',
    },
    'workflow_edit_status_draft': {'ar': 'مسوّدة', 'en': 'Draft'},
    'workflow_edit_status_pending': {'ar': 'معلق', 'en': 'Pending'},
    'workflow_edit_status_approved': {'ar': 'معتمد', 'en': 'Approved'},
    'workflow_edit_status_rejected': {'ar': 'مرفوض', 'en': 'Rejected'},
    'workflow_edit_status_returned_for_edit': {
      'ar': 'يعيد تعديل',
      'en': 'Returned for edit',
    },
    'workflow_edit_status_cancelled': {'ar': 'ملغى', 'en': 'Cancelled'},
    'workflow_edit_saved_file_label': {'ar': 'ملف محفوظ', 'en': 'Saved file'},
    'workflow_edit_no_file_chosen': {
      'ar': 'لم يُختار ملف',
      'en': 'No file chosen',
    },
    'workflow_edit_manager_note_title': {
      'ar': 'ملاحظة المدير المباشر:',
      'en': 'Direct manager note:',
    },
    'workflow_edit_manager_note_default': {
      'ar': 'يُرجى تعديل الطلب وإعادة إرساله.',
      'en': 'Please edit the request and resubmit it.',
    },
    'workflow_edit_notes_label': {'ar': 'ملاحظات التعديل', 'en': 'Edit Notes'},
    'workflow_edit_notes_hint': {
      'ar': 'اكتب ملاحظاتك على التعديل',
      'en': 'Write your notes about the edit',
    },
    'workflow_edit_resave_button': {
      'ar': 'حفظ وإعادة إرسال',
      'en': 'Save and Resend',
    },
    'workflow_edit_save_button': {'ar': 'حفظ التعديل', 'en': 'Save Edit'},
    'workflow_edit_saving': {'ar': 'جاري الحفظ...', 'en': 'Saving...'},
    'workflow_edit_amount_required': {'ar': 'مطلوب', 'en': 'Required'},
    'workflow_edit_amount_invalid': {
      'ar': 'المبلغ غير صالح',
      'en': 'Invalid amount',
    },
    'workflow_edit_advance_exhausted': {
      'ar': 'تم استنفاد حد السلفة',
      'en': 'Advance limit exhausted',
    },
    'workflow_edit_amount_exceeds_prefix': {
      'ar': 'المبلغ أعلى من المسموح: ',
      'en': 'Amount exceeds the allowed limit: ',
    },
    'workflow_edit_title_prefix': {'ar': 'تعديل ', 'en': 'Edit '},
    'workflow_edit_title_fallback': {'ar': 'الطلب', 'en': 'Request'},
    'workflow_edit_no_data': {'ar': 'لا يوجد بيانات', 'en': 'No data'},
    'workflow_edit_cannot_edit_prefix': {
      'ar': 'لا يمكن تعديل الطلب في الحالة الحالية: ',
      'en': 'The request cannot be edited in its current status: ',
    },

    // العهدة والأصول (assets_screen.dart)
    'assets_load_error': {
      'ar': 'تعذر تحميل بيانات العهدة',
      'en': 'Could not load custody data',
    },
    'assets_returned_default': {
      'ar': 'تم تحديث حالة العهدة',
      'en': 'Custody status updated',
    },
    'assets_empty': {
      'ar': 'لا توجد عهد مسجّلة',
      'en': 'No registered custody items',
    },
    'assets_held_by_label': {'ar': 'العهدة لدى: ', 'en': 'Held by: '},
    'assets_return_button': {'ar': 'استرجاع', 'en': 'Return'},

    // تسليم عهدة (custody_issue_screen.dart)
    'custody_issue_item_not_found': {
      'ar': 'صنف غير موجود',
      'en': 'Item not found',
    },
    'custody_issue_search_error': {'ar': 'تعذّر البحث', 'en': 'Search failed'},
    'custody_issue_success': {
      'ar': 'تم تسليم العهدة',
      'en': 'Custody issued successfully',
    },
    'custody_issue_failed': {
      'ar': 'فشل تسليم العهدة',
      'en': 'Failed to issue custody',
    },
    'custody_issue_submit_error': {
      'ar': 'تعذّر الإرسال',
      'en': 'Could not submit',
    },
    'custody_issue_employee_label': {'ar': 'الموظّف', 'en': 'Employee'},
    'custody_issue_search_hint': {
      'ar': 'ابحث بالاسم...',
      'en': 'Search by name...',
    },
    'custody_issue_no_results': {
      'ar': 'لا توجد نتائج',
      'en': 'No results found',
    },
    'custody_issue_title': {'ar': 'تسليم عهدة', 'en': 'Issue Custody'},
    'custody_issue_barcode_label': {'ar': 'باركود الصنف', 'en': 'Item Barcode'},
    'custody_issue_warehouse_label': {'ar': 'المخزن', 'en': 'Warehouse'},
    'custody_issue_qty_label': {'ar': 'الكمية', 'en': 'Quantity'},
    'custody_issue_employee_segment': {'ar': 'موظّف', 'en': 'Employee'},
    'custody_issue_department_segment': {'ar': 'قسم', 'en': 'Department'},
    'custody_issue_clothing_employee_only': {
      'ar': 'الملابس تُسلّم فقط لموظّف',
      'en': 'Clothing is issued to an employee only',
    },
    'custody_issue_department_label': {'ar': 'القسم', 'en': 'Department'},
    'custody_issue_submit_button': {'ar': 'تسليم عهدة', 'en': 'Issue Custody'},

    // طلب صيانة جديد (maintenance_create_screen.dart)
    'maintenance_create_priority_low': {'ar': 'منخفضة', 'en': 'Low'},
    'maintenance_create_priority_normal': {'ar': 'عادية', 'en': 'Normal'},
    'maintenance_create_priority_high': {'ar': 'عالية', 'en': 'High'},
    'maintenance_create_priority_urgent': {'ar': 'عاجلة', 'en': 'Urgent'},
    'maintenance_create_capture_from_camera': {
      'ar': 'التقاط صورة من الكاميرا',
      'en': 'Take a photo from the camera',
    },
    'maintenance_create_pick_from_gallery': {
      'ar': 'اختيار من المعرض',
      'en': 'Choose from gallery',
    },
    'maintenance_create_photo_required': {
      'ar': 'يجب إرفاق صورة من الكاميرا',
      'en': 'A photo from the camera must be attached',
    },
    'maintenance_create_submitted_prefix': {
      'ar': 'تم إرسال طلب الصيانة: ',
      'en': 'Maintenance request sent: ',
    },
    'maintenance_create_submit_failed': {
      'ar': 'فشل إرسال الطلب',
      'en': 'Failed to send the request',
    },
    'maintenance_create_submit_error': {
      'ar': 'تعذر إرسال الطلب، تحقق من الاتصال',
      'en': 'Could not send the request, check your connection',
    },
    'maintenance_create_title': {
      'ar': 'طلب صيانة جديد',
      'en': 'New Maintenance Request',
    },
    'maintenance_create_requester_name_label': {
      'ar': 'اسم مقدم الطلب',
      'en': "Requester's Name",
    },
    'maintenance_create_job_title_label': {'ar': 'الوظيفة', 'en': 'Job Title'},
    'maintenance_create_department_label': {'ar': 'القسم', 'en': 'Department'},
    'maintenance_create_fault_name_label': {
      'ar': 'اسم العطل *',
      'en': 'Fault Name *',
    },
    'maintenance_create_required_field': {'ar': 'مطلوب', 'en': 'Required'},
    'maintenance_create_device_name_label': {
      'ar': 'اسم الجهاز *',
      'en': 'Device Name *',
    },
    'maintenance_create_priority_label': {
      'ar': 'الأهمية *',
      'en': 'Priority *',
    },
    'maintenance_create_additional_notes_label': {
      'ar': 'ملاحظات إضافية',
      'en': 'Additional Notes',
    },
    'maintenance_create_fault_photo_label': {
      'ar': 'صورة العطل *',
      'en': 'Fault Photo *',
    },
    'maintenance_create_attach_photo_button': {
      'ar': 'إرفاق صورة (كاميرا أو معرض)',
      'en': 'Attach photo (camera or gallery)',
    },
    'maintenance_create_change_photo_button': {
      'ar': 'تغيير الصورة',
      'en': 'Change Photo',
    },
    'maintenance_create_sending': {'ar': 'جاري الإرسال...', 'en': 'Sending...'},
    'maintenance_create_submit_button': {
      'ar': 'إرسال الطلب',
      'en': 'Submit Request',
    },

    // تفاصيل طلب الصيانة (maintenance_detail_screen.dart)
    'maintenance_detail_status_pending': {'ar': 'معلق', 'en': 'Pending'},
    'maintenance_detail_status_assigned': {'ar': 'معين', 'en': 'Assigned'},
    'maintenance_detail_status_in_progress': {
      'ar': 'قيد التنفيذ',
      'en': 'In Progress',
    },
    'maintenance_detail_status_completed': {'ar': 'منتهي', 'en': 'Completed'},
    'maintenance_detail_status_confirmed': {
      'ar': 'تم التأكيد',
      'en': 'Confirmed',
    },
    'maintenance_detail_status_cancelled': {'ar': 'ملغى', 'en': 'Cancelled'},
    'maintenance_detail_priority_low': {'ar': 'منخفضة', 'en': 'Low'},
    'maintenance_detail_priority_normal': {'ar': 'عادية', 'en': 'Normal'},
    'maintenance_detail_priority_high': {'ar': 'عالية', 'en': 'High'},
    'maintenance_detail_priority_urgent': {'ar': 'عاجلة', 'en': 'Urgent'},
    'maintenance_detail_work_status_parts_requested': {
      'ar': 'تم طلب قطع الغيار',
      'en': 'Parts requested',
    },
    'maintenance_detail_work_status_in_maintenance': {
      'ar': 'قيد الصيانة',
      'en': 'Under maintenance',
    },
    'maintenance_detail_work_status_external_maintenance': {
      'ar': 'صيانة خارجية',
      'en': 'External maintenance',
    },
    'maintenance_detail_work_status_completed': {
      'ar': 'إتمام الصيانة',
      'en': 'Maintenance completed',
    },
    'maintenance_detail_load_error': {
      'ar': 'تعذر تحميل تفاصيل الطلب',
      'en': 'Could not load request details',
    },
    'maintenance_detail_update_status_title': {
      'ar': 'تحديث حالة الصيانة',
      'en': 'Update Maintenance Status',
    },
    'maintenance_detail_status_label': {'ar': 'الحالة *', 'en': 'Status *'},
    'maintenance_detail_notes_tools_label': {
      'ar': 'الملاحظات والأدوات المستخدمة',
      'en': 'Notes and tools used',
    },
    'maintenance_detail_cost_label': {
      'ar': 'تكلفة الصيانة (اختياري)',
      'en': 'Maintenance cost (optional)',
    },
    'maintenance_detail_cancel_button': {'ar': 'إلغاء', 'en': 'Cancel'},
    'maintenance_detail_save_button': {'ar': 'حفظ', 'en': 'Save'},
    'maintenance_detail_no_maintenance_employees': {
      'ar': 'لا يوجد موظفون في قسم الصيانة',
      'en': 'No employees in the maintenance department',
    },
    'maintenance_detail_search_by_name': {
      'ar': 'ابحث بالاسم...',
      'en': 'Search by name...',
    },
    'maintenance_detail_no_results': {
      'ar': 'لا توجد نتائج',
      'en': 'No results found',
    },
    'maintenance_detail_title': {
      'ar': 'تفاصيل طلب الصيانة',
      'en': 'Maintenance Request Details',
    },
    'maintenance_detail_number_label': {'ar': 'الرقم', 'en': 'Number'},
    'maintenance_detail_fault_label': {'ar': 'العطل', 'en': 'Fault'},
    'maintenance_detail_device_label': {'ar': 'الجهاز', 'en': 'Device'},
    'maintenance_detail_status_row_label': {'ar': 'الحالة', 'en': 'Status'},
    'maintenance_detail_priority_row_label': {
      'ar': 'الأهمية',
      'en': 'Priority',
    },
    'maintenance_detail_requester_label': {
      'ar': 'صاحب الطلب',
      'en': 'Requester',
    },
    'maintenance_detail_assigned_label': {'ar': 'المعين', 'en': 'Assigned To'},
    'maintenance_detail_work_status_row_label': {
      'ar': 'حالة الصيانة',
      'en': 'Maintenance Status',
    },
    'maintenance_detail_notes_label': {'ar': 'ملاحظات', 'en': 'Notes'},
    'maintenance_detail_resolution_notes_label': {
      'ar': 'الملاحظات والأدوات المستخدمة',
      'en': 'Notes and tools used',
    },
    'maintenance_detail_cost_row_label': {
      'ar': 'تكلفة الصيانة',
      'en': 'Maintenance cost',
    },
    'maintenance_detail_image_load_error': {
      'ar': 'تعذر تحميل الصورة',
      'en': 'Could not load the image',
    },
    'maintenance_detail_assign_button': {
      'ar': 'تعيين موظف',
      'en': 'Assign Employee',
    },
    'maintenance_detail_start_button': {
      'ar': 'الموافقة وبدء المهمة',
      'en': 'Approve and Start Task',
    },
    'maintenance_detail_update_status_button': {
      'ar': 'تحديث حالة الصيانة',
      'en': 'Update Maintenance Status',
    },
    'maintenance_detail_confirm_button': {
      'ar': 'تأكيد إنجاز العمل',
      'en': 'Confirm Work Completion',
    },
    'maintenance_detail_fallback_dash': {'ar': '—', 'en': '—'},

    // قائمة طلبات الصيانة (maintenance_screen.dart)
    'maintenance_list_status_pending': {'ar': 'معلق', 'en': 'Pending'},
    'maintenance_list_status_assigned': {'ar': 'معين', 'en': 'Assigned'},
    'maintenance_list_status_in_progress': {
      'ar': 'قيد التنفيذ',
      'en': 'In Progress',
    },
    'maintenance_list_status_completed': {'ar': 'منتهي', 'en': 'Completed'},
    'maintenance_list_status_confirmed': {
      'ar': 'تم التأكيد',
      'en': 'Confirmed',
    },
    'maintenance_list_status_cancelled': {'ar': 'ملغى', 'en': 'Cancelled'},
    'maintenance_list_load_error': {
      'ar': 'تعذر تحميل طلبات الصيانة',
      'en': 'Could not load maintenance requests',
    },
    'maintenance_list_empty': {
      'ar': 'لا توجد طلبات صيانة',
      'en': 'No maintenance requests',
    },
    'maintenance_list_device_label': {'ar': 'الجهاز: ', 'en': 'Device: '},
    'maintenance_list_requester_label': {
      'ar': 'صاحب الطلب: ',
      'en': 'Requester: ',
    },
    'maintenance_list_assigned_label': {'ar': 'المعين: ', 'en': 'Assigned: '},
    'maintenance_list_fab_label': {
      'ar': 'طلب صيانة',
      'en': 'Maintenance Request',
    },

    // PTT: مدير البث المباشر (ptt_live_manager.dart)
    'ptt_live_status_connected': {'ar': 'متصل', 'en': 'Connected'},
    'ptt_live_status_disconnected': {'ar': 'غير متصل', 'en': 'Disconnected'},

    // PTT: الشاشة الرئيسية (ptt_screen.dart)
    'ptt_channels_load_error': {
      'ar': 'تعذر تحميل قنوات PTT',
      'en': 'Could not load PTT channels',
    },
    'ptt_channel_load_error': {
      'ar': 'تعذر تحميل القناة',
      'en': 'Could not load the channel',
    },
    'ptt_play_error': {
      'ar': 'تعذر تشغيل الرسالة',
      'en': 'Could not play the message',
    },
    'ptt_mic_permission_required': {
      'ar': 'يجب السماح بالميكروفون للبث/الاستماع',
      'en': 'Microphone access must be allowed to broadcast/listen',
    },
    'ptt_no_livekit_token': {
      'ar': 'لم يُرجع الخادم توكن LiveKit',
      'en': 'The server did not return a LiveKit token',
    },
    'ptt_live_failed_prefix': {
      'ar': 'فشل البث المباشر: ',
      'en': 'Live broadcast failed: ',
    },
    'ptt_record_start_error': {
      'ar': 'تعذر بدء التسجيل',
      'en': 'Could not start recording',
    },
    'ptt_record_stop_error': {
      'ar': 'تعذر إيقاف التسجيل',
      'en': 'Could not stop recording',
    },
    'ptt_send_error': {
      'ar': 'تعذر إرسال الرسالة',
      'en': 'Could not send the message',
    },
    'ptt_title': {'ar': 'PTT', 'en': 'PTT'},
    'ptt_channel_fallback_title': {'ar': 'القناة', 'en': 'Channel'},
    'ptt_no_channels_available': {
      'ar': 'لا توجد قنوات PTT متاحة لصلاحياتك.',
      'en': 'No PTT channels available for your permissions.',
    },
    'ptt_priority_label': {'ar': 'الأولوية', 'en': 'Priority'},
    'ptt_empty_can_send': {
      'ar': 'لا توجد رسائل.\nاضغط زر التحدث أدناه لإرسال رسالة.',
      'en': 'No messages.\nPress the talk button below to send a message.',
    },
    'ptt_empty_cannot_send': {
      'ar': 'لا توجد رسائل في هذه القناة.\nليس لديك صلاحية الإرسال.',
      'en': "No messages in this channel.\nYou don't have permission to send.",
    },
    'ptt_live_label_prefix': {
      'ar': 'البث المباشر — ',
      'en': 'Live broadcast — ',
    },
    'ptt_live_participant_suffix': {'ar': ' مشارك)', 'en': ' participants)'},
    'ptt_talking_now': {'ar': '🔴 تتحدث الآن…', 'en': '🔴 Talking now…'},
    'ptt_hold_to_broadcast': {
      'ar': '🎙️ اضغط مطولاً للبث الحي',
      'en': '🎙️ Press and hold for live broadcast',
    },
    'ptt_stop_button': {'ar': 'إيقاف', 'en': 'Stop'},
    'ptt_live_listen_button': {'ar': 'استماع مباشر', 'en': 'Listen Live'},
    'ptt_live_start_button': {'ar': 'بدء البث', 'en': 'Start Broadcast'},
    'ptt_talking': {'ar': 'جارٍ التحدث...', 'en': 'Talking...'},
    'ptt_hold_to_talk': {
      'ar': 'اضغط مع الاستمرار للتحدث',
      'en': 'Press and hold to talk',
    },
    'ptt_no_send_permission': {
      'ar': 'ليس لديك صلاحية الإرسال',
      'en': "You don't have permission to send",
    },

    // WebView (webview_screen.dart)
    'webview_not_logged_in': {
      'ar': 'لم يتم تسجيل الدخول',
      'en': 'Not logged in',
    },
    'webview_load_failed': {
      'ar': 'فشل تحميل الصفحة',
      'en': 'Failed to load the page',
    },

    // ودجت مسح الباركود (barcode_scanner.dart)
    'barcode_scanner_tooltip': {'ar': 'مسح الباركود', 'en': 'Scan Barcode'},

    // قائمة خيارات عضو الفريق (team_evaluation_screen.dart)
    'hr_team_options_evaluate': {
      'ar': 'تقييم الموظف',
      'en': 'Evaluate Employee',
    },
    'hr_team_options_request_on_behalf': {
      'ar': 'تقديم طلب نيابة عن الموظف',
      'en': 'Submit Request on Behalf',
    },

    // شاشة تقديم طلب نيابة عن عضو الفريق (team_request_screen.dart)
    'team_request_title_prefix': {
      'ar': 'طلب نيابة عن: ',
      'en': 'Request on behalf of: ',
    },
    'team_request_tab_attendance': {
      'ar': 'نسيان بصمة',
      'en': 'Missing Attendance',
    },
    'team_request_tab_leave': {'ar': 'إجازة', 'en': 'Leave'},
    'team_request_att_type_label': {'ar': 'نوع الطلب', 'en': 'Request Type'},
    'team_request_att_type_in': {'ar': 'بصمة دخول', 'en': 'Clock In'},
    'team_request_att_type_out': {'ar': 'بصمة خروج', 'en': 'Clock Out'},
    'team_request_att_type_both': {'ar': 'دخول وخروج', 'en': 'Clock In & Out'},
    'team_request_att_clock_in_label': {
      'ar': 'وقت بصمة الدخول',
      'en': 'Clock In Time',
    },
    'team_request_att_clock_out_label': {
      'ar': 'وقت بصمة الخروج',
      'en': 'Clock Out Time',
    },
    'team_request_choose_datetime': {
      'ar': 'اختر التاريخ والوقت',
      'en': 'Choose date & time',
    },
    'team_request_choose_date': {'ar': 'اختر التاريخ', 'en': 'Choose date'},
    'team_request_reason_label': {'ar': 'السبب', 'en': 'Reason'},
    'team_request_submit_button': {'ar': 'إرسال الطلب', 'en': 'Submit Request'},
    'team_request_submitting': {'ar': 'جارٍ الإرسال...', 'en': 'Submitting...'},
    'team_request_att_clock_in_required': {
      'ar': 'يرجى تحديد وقت بصمة الدخول',
      'en': 'Please choose clock-in time',
    },
    'team_request_att_clock_out_required': {
      'ar': 'يرجى تحديد وقت بصمة الخروج',
      'en': 'Please choose clock-out time',
    },
    'team_request_att_both_required': {
      'ar': 'يرجى تحديد وقتي الدخول والخروج معاً',
      'en': 'Please choose both clock-in and clock-out time',
    },
    'team_request_att_reason_required': {
      'ar': 'السبب / الملاحظات حقل مطلوب',
      'en': 'Reason / notes is required',
    },
    'team_request_att_submitted': {
      'ar': 'تم إرسال طلب نسيان البصمة بنجاح',
      'en': 'Missing attendance request submitted successfully',
    },
    'team_request_submit_failed': {
      'ar': 'فشل إرسال الطلب',
      'en': 'Failed to submit request',
    },
    'team_request_submit_error': {
      'ar': 'حدث خطأ أثناء إرسال الطلب',
      'en': 'An error occurred while submitting the request',
    },
    'team_request_leave_type_label': {'ar': 'نوع الإجازة', 'en': 'Leave Type'},
    'team_request_leave_type_unpaid': {'ar': 'بدون راتب', 'en': 'Unpaid'},
    'team_request_leave_type_hourly': {'ar': 'ساعية', 'en': 'Hourly'},
    'team_request_leave_type_emergency': {'ar': 'طارئة', 'en': 'Emergency'},
    'team_request_leave_type_sick': {'ar': 'مرضية', 'en': 'Sick'},
    'team_request_leave_type_annual': {'ar': 'سنوية', 'en': 'Annual'},
    'team_request_leave_from_label': {'ar': 'من تاريخ', 'en': 'From Date'},
    'team_request_leave_to_label': {'ar': 'إلى تاريخ', 'en': 'To Date'},
    'team_request_leave_dates_required': {
      'ar': 'يرجى تحديد تاريخ البدء والانتهاء',
      'en': 'Please choose start and end dates',
    },
    'team_request_leave_dates_invalid': {
      'ar': 'تاريخ الانتهاء يجب أن يكون بعد تاريخ البدء',
      'en': 'End date must be after start date',
    },
    'team_request_leave_submitted': {
      'ar': 'تم إرسال طلب الإجازة بنجاح',
      'en': 'Leave request submitted successfully',
    },
  };

  static String t(String key, String languageCode) {
    return _values[key]?[languageCode] ?? _values[key]?['ar'] ?? key;
  }
}
