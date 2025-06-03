import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:awesome_dialog/awesome_dialog.dart';
import 'package:google_fonts/google_fonts.dart';
import 'dart:convert';
import 'package:device_info_plus/device_info_plus.dart';

class RegisterFormPage extends StatefulWidget {
  const RegisterFormPage({Key? key}) : super(key: key);

  @override
  State<RegisterFormPage> createState() => _RegisterFormPageState();
}

class _RegisterFormPageState extends State<RegisterFormPage> {
  int _currentStep = 0;
  final int _totalSteps = 5;

  // State untuk semua data form
  final Map<String, dynamic> formData = {};

  // Form keys untuk validasi antar step
  final List<GlobalKey<FormState>> _formKeys = List.generate(5, (_) => GlobalKey<FormState>());

  // Tambahkan List<Function> _onNextCallbacks di RegisterFormPage untuk menyimpan callback dari setiap step
  final List<Function> _onNextCallbacks = [];

  // 1. Buat GlobalKey untuk setiap step
  final _step1Key = GlobalKey<_Step1DataPribadiState>();
  final _step2Key = GlobalKey<_Step2KontakKeluargaState>();
  final _step3Key = GlobalKey<_Step3PendidikanPekerjaanState>();
  final _step4Key = GlobalKey<_Step4DokumenState>();

  String? deviceId;

  @override
  void initState() {
    super.initState();
    _initDeviceId();
  }

  Future<void> _initDeviceId() async {
    final deviceInfo = DeviceInfoPlugin();
    String? id;
    if (Platform.isAndroid) {
      final androidInfo = await deviceInfo.androidInfo;
      id = androidInfo.id;
    } else if (Platform.isIOS) {
      final iosInfo = await deviceInfo.iosInfo;
      id = iosInfo.identifierForVendor;
    }
    setState(() {
      deviceId = id;
    });
  }

  void _nextStep() {
    if (_formKeys[_currentStep].currentState?.validate() ?? true) {
      Map<String, dynamic> data = {};
      if (_currentStep == 0) data = _step1Key.currentState?.getFormData() ?? {};
      if (_currentStep == 1) data = _step2Key.currentState?.getFormData() ?? {};
      if (_currentStep == 2) data = _step3Key.currentState?.getFormData() ?? {};
      if (_currentStep == 3) data = _step4Key.currentState?.getFormData() ?? {};
      setState(() {
        formData.addAll(data);
        _currentStep++;
      });
    }
  }

  void _prevStep() {
    if (_currentStep > 0) {
      setState(() {
        _currentStep--;
      });
    }
  }

  void _onStepData(String step, Map<String, dynamic> data) {
    setState(() {
      formData.addAll(data);
    });
  }

  Future<void> _submit() async {
    // Validasi terakhir
    if (!(_formKeys[4].currentState?.validate() ?? true)) return;
    // Konfirmasi SweetAlert
    AwesomeDialog(
      context: context,
      dialogType: DialogType.question,
      animType: AnimType.bottomSlide,
      title: 'Konfirmasi',
      desc: 'Apakah data yang diisi sudah benar?',
      btnCancelOnPress: () {},
      btnOkText: 'Yes',
      btnCancelText: 'No',
      btnOkOnPress: () async {
        // Tampilkan loading
        showDialog(
          context: context,
          barrierDismissible: false,
          builder: (ctx) => const Center(child: CircularProgressIndicator()),
        );
        try {
          var uri = Uri.parse('http://10.0.2.2:8000/api/mobile/register');
          var request = http.MultipartRequest('POST', uri);
          request.headers['Accept'] = 'application/json';

          // Mapping field Flutter ke field backend
          final Map<String, dynamic> mappedData = {
            'no_ktp': formData['noKtp'] ?? '',
            'nama_lengkap': formData['nama'] ?? '',
            'email': formData['email'] ?? '',
            'password': formData['password'] ?? '',
            'jenis_kelamin': formData['jenisKelamin'] ?? '',
            'tempat_lahir': formData['tempatLahir'] ?? '',
            'tanggal_lahir': formData['tanggalLahir'] != null ? (formData['tanggalLahir'] is DateTime ? (formData['tanggalLahir'] as DateTime).toIso8601String().substring(0,10) : formData['tanggalLahir'].toString().substring(0,10)) : '',
            'agama': formData['agama'] ?? '',
            'status_pernikahan': formData['statusPernikahan'] ?? '',
            'alamat': formData['alamatDomisili'] ?? '',
            'alamat_ktp': formData['alamatKtp'] ?? '',
            'golongan_darah': formData['golonganDarah'] ?? '',
            'no_hp': formData['noHp'] ?? '',
            'nama_kontak_darurat': formData['namaKontakDarurat'] ?? '',
            'no_hp_kontak_darurat': formData['noHpKontakDarurat'] ?? '',
            'hubungan_kontak_darurat': formData['hubunganKontakDarurat'] ?? '',
            'jumlah_anak': formData['jumlahAnak'] ?? '',
            'nomor_kk': formData['noKk'] ?? '',
            'nama_rekening': formData['namaRekening'] ?? '',
            'no_rekening': formData['noRekening'] ?? '',
            'npwp_number': formData['npwp'] ?? '',
            'bpjs_health_number': formData['bpjsKesehatan'] ?? '',
            'bpjs_employment_number': formData['bpjsKetenagakerjaan'] ?? '',
            'last_education': formData['pendidikanTerakhir'] ?? '',
            'name_school_college': formData['namaSekolah'] ?? '',
            'school_college_major': formData['jurusan'] ?? '',
            'id_jabatan': formData['idJabatan'] ?? '',
            'position': formData['idJabatan'] ?? '',
            'work_start_date': formData['workStartDate'] != null ? (formData['workStartDate'] is DateTime ? (formData['workStartDate'] as DateTime).toIso8601String().substring(0,10) : formData['workStartDate'].toString().substring(0,10)) : '',
            'imei': deviceId ?? '',
          };

          mappedData.forEach((key, value) {
            if (value != null && value.toString().isNotEmpty) {
              request.fields[key] = value.toString();
            }
          });

          // File upload
          if (formData['fotoKtp'] != null) {
            request.files.add(await http.MultipartFile.fromPath('foto_ktp', formData['fotoKtp'].path));
          }
          if (formData['fotoKk'] != null) {
            request.files.add(await http.MultipartFile.fromPath('foto_kk', formData['fotoKk'].path));
          }
          if (formData['fotoBerwarna'] != null) {
            request.files.add(await http.MultipartFile.fromPath('upload_latest_color_photo', formData['fotoBerwarna'].path));
          }
          if (formData['avatarFile'] != null) {
            request.files.add(await http.MultipartFile.fromPath('avatar', formData['avatarFile'].path));
          }

          var response = await request.send();
          final respStr = await response.stream.bytesToString();
          Navigator.of(context).pop(); // Tutup loading
          if (response.statusCode == 200) {
            AwesomeDialog(
              context: context,
              dialogType: DialogType.success,
              animType: AnimType.bottomSlide,
              title: 'Sukses',
              desc: 'Registrasi berhasil! Silakan login.',
              btnOkOnPress: () {
                Navigator.of(context).pushNamedAndRemoveUntil('/login', (route) => false);
              },
            ).show();
          } else {
            AwesomeDialog(
              context: context,
              dialogType: DialogType.error,
              animType: AnimType.bottomSlide,
              title: 'Gagal',
              desc: 'Registrasi gagal! Pesan: $respStr',
              btnOkOnPress: () {},
            ).show();
          }
        } catch (e) {
          Navigator.of(context).pop(); // Tutup loading
          AwesomeDialog(
            context: context,
            dialogType: DialogType.error,
            animType: AnimType.bottomSlide,
            title: 'Error',
            desc: 'Terjadi error: $e',
            btnOkOnPress: () {},
          ).show();
        }
      },
    ).show();
  }

  List<Widget> get _steps => [
    _Step1DataPribadi(
      key: _step1Key,
      onNext: (data) { _onStepData('step1', data); },
      initialData: formData,
      formKey: _formKeys[0],
    ),
    _Step2KontakKeluarga(
      key: _step2Key,
      onNext: (data) { _onStepData('step2', data); },
      initialData: formData,
      formKey: _formKeys[1],
    ),
    _Step3PendidikanPekerjaan(
      key: _step3Key,
      onNext: (data) { _onStepData('step3', data); },
      initialData: formData,
      formKey: _formKeys[2],
    ),
    _Step4Dokumen(
      key: _step4Key,
      onNext: (data) { _onStepData('step4', data); },
      initialData: formData,
      formKey: _formKeys[3],
    ),
    _Step5ReviewSubmit(formData: formData, onSubmit: _submit, formKey: _formKeys[4]),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Registrasi Karyawan'),
        backgroundColor: Colors.blue[700],
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            // Progress indicator
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(_totalSteps, (i) => Container(
                margin: const EdgeInsets.symmetric(horizontal: 4),
                width: 28,
                height: 8,
                decoration: BoxDecoration(
                  color: i <= _currentStep ? Colors.blue[700] : Colors.blue[100],
                  borderRadius: BorderRadius.circular(8),
                ),
              )),
            ),
            const SizedBox(height: 18),
            Expanded(child: _steps[_currentStep]),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                if (_currentStep > 0)
                  ElevatedButton(
                    onPressed: _prevStep,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.grey[300],
                      foregroundColor: Colors.black,
                    ),
                    child: const Text('Sebelumnya'),
                  )
                else
                  const SizedBox(width: 120),
                if (_currentStep < _totalSteps - 1)
                  ElevatedButton(
                    onPressed: _nextStep,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.blue[700],
                      foregroundColor: Colors.white,
                    ),
                    child: const Text('Selanjutnya'),
                  )
                else
                  ElevatedButton(
                    onPressed: _submit,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.green[700],
                      foregroundColor: Colors.white,
                    ),
                    child: const Text('Submit'),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Step1DataPribadi extends StatefulWidget {
  final Map<String, dynamic> initialData;
  final GlobalKey<FormState> formKey;
  final void Function(Map<String, dynamic>) onNext;

  const _Step1DataPribadi({Key? key, required this.initialData, required this.formKey, required this.onNext}) : super(key: key);

  @override
  State<_Step1DataPribadi> createState() => _Step1DataPribadiState();
}

class _Step1DataPribadiState extends State<_Step1DataPribadi> with SingleTickerProviderStateMixin {
  final TextEditingController namaController = TextEditingController();
  final TextEditingController noKtpController = TextEditingController();
  String? jenisKelamin;
  final TextEditingController tempatLahirController = TextEditingController();
  DateTime? tanggalLahir;
  String? agama;
  String? golonganDarah;
  String? statusPernikahan;
  final TextEditingController alamatDomisiliController = TextEditingController();
  final TextEditingController alamatKtpController = TextEditingController();
  DateTime? workStartDate;
  File? avatarFile;

  // Tambahkan state untuk jabatan
  List<Map<String, dynamic>> daftarJabatan = [];
  String? selectedJabatanId;

  late AnimationController _animController;
  late Animation<double> _cardAnim;

  @override
  void initState() {
    super.initState();
    namaController.text = widget.initialData['nama'] ?? '';
    noKtpController.text = widget.initialData['noKtp'] ?? '';
    jenisKelamin = widget.initialData['jenisKelamin'];
    tempatLahirController.text = widget.initialData['tempatLahir'] ?? '';
    tanggalLahir = widget.initialData['tanggalLahir'];
    agama = widget.initialData['agama'];
    golonganDarah = widget.initialData['golonganDarah'];
    statusPernikahan = widget.initialData['statusPernikahan'];
    alamatDomisiliController.text = widget.initialData['alamatDomisili'] ?? '';
    alamatKtpController.text = widget.initialData['alamatKtp'] ?? '';
    selectedJabatanId = widget.initialData['idJabatan'];
    workStartDate = widget.initialData['workStartDate'];
    _animController = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _cardAnim = CurvedAnimation(parent: _animController, curve: Curves.easeOutBack);
    _animController.forward();
    _fetchJabatan();
  }

  Future<void> _fetchJabatan() async {
    try {
      final response = await http.get(Uri.parse('http://10.0.2.2:8000/api/jabatan'));
      if (response.statusCode == 200) {
        final List data = json.decode(response.body);
        setState(() {
          daftarJabatan = data.map((e) => {
            'id_jabatan': e['id_jabatan'].toString(),
            'nama_jabatan': e['nama_jabatan'],
          }).toList();
        });
      }
    } catch (e) {
      // Bisa tampilkan error jika perlu
    }
  }

  @override
  void dispose() {
    namaController.dispose();
    noKtpController.dispose();
    tempatLahirController.dispose();
    alamatDomisiliController.dispose();
    alamatKtpController.dispose();
    _animController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      height: MediaQuery.of(context).size.height * 0.85,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFe3f0ff), Color(0xFFf8fbff), Color(0xFFe3f0ff)],
        ),
      ),
      child: Center(
        child: ScaleTransition(
          scale: _cardAnim,
          child: Card(
            elevation: 16,
            shadowColor: Colors.blue.withOpacity(0.2),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(32)),
            color: Colors.white.withOpacity(0.95),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
              child: SingleChildScrollView(
                padding: const EdgeInsets.only(bottom: 32),
                child: Form(
                  key: widget.formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Data Pribadi',
                          style: GoogleFonts.poppins(
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                            color: Colors.blue[800],
                          )),
                      const SizedBox(height: 18),
                      _roundedInput(
                        controller: namaController,
                        label: 'Nama lengkap sesuai KTP',
                        icon: Icons.person,
                      ),
                      const SizedBox(height: 14),
                      SizedBox(
                        width: double.infinity,
                        child: DropdownButtonFormField<String>(
                          isExpanded: true,
                          value: selectedJabatanId,
                          items: daftarJabatan.map((jabatan) => DropdownMenuItem<String>(
                            value: jabatan['id_jabatan'],
                            child: Text(jabatan['nama_jabatan'], style: GoogleFonts.poppins(), overflow: TextOverflow.ellipsis),
                          )).toList(),
                          onChanged: (v) => setState(() => selectedJabatanId = v),
                          decoration: InputDecoration(
                            labelText: 'Jabatan/Posisi',
                            prefixIcon: Icon(Icons.work, color: Colors.blue[400]),
                            filled: true,
                            fillColor: Colors.blue[50]?.withOpacity(0.2),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(18),
                              borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(18),
                              borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(18),
                              borderSide: BorderSide(color: Colors.blue[400]!, width: 2),
                            ),
                            isDense: true,
                            contentPadding: EdgeInsets.symmetric(vertical: 14, horizontal: 12),
                          ),
                          validator: (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
                        ),
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: noKtpController,
                        label: 'No KTP',
                        icon: Icons.badge_outlined,
                      ),
                      const SizedBox(height: 14),
                      const Text('Jenis kelamin *', style: TextStyle(fontWeight: FontWeight.w500)),
                      Row(
                        children: [
                          Radio<String>(
                            value: 'laki-laki',
                            groupValue: jenisKelamin,
                            onChanged: (v) => setState(() => jenisKelamin = v),
                          ),
                          const Text('Laki-laki'),
                          Radio<String>(
                            value: 'perempuan',
                            groupValue: jenisKelamin,
                            onChanged: (v) => setState(() => jenisKelamin = v),
                          ),
                          const Text('Perempuan'),
                        ],
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: tempatLahirController,
                        label: 'Tempat Lahir',
                        icon: Icons.location_on_outlined,
                      ),
                      const SizedBox(height: 14),
                      GestureDetector(
                        onTap: () async {
                          final picked = await showDatePicker(
                            context: context,
                            initialDate: DateTime(2000, 1, 1),
                            firstDate: DateTime(1950),
                            lastDate: DateTime.now(),
                          );
                          if (picked != null) setState(() => tanggalLahir = picked);
                        },
                        child: AbsorbPointer(
                          child: _roundedInput(
                            controller: TextEditingController(
                              text: tanggalLahir == null ? '' : '${tanggalLahir!.year}-${tanggalLahir!.month.toString().padLeft(2, '0')}-${tanggalLahir!.day.toString().padLeft(2, '0')}',
                            ),
                            label: 'Tanggal Lahir',
                            icon: Icons.calendar_today,
                          ),
                        ),
                      ),
                      const SizedBox(height: 14),
                      _roundedDropdown(
                        value: agama,
                        items: ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Budha', 'Lainnya'],
                        label: 'Agama',
                        icon: Icons.account_balance,
                        onChanged: (v) => setState(() => agama = v),
                      ),
                      const SizedBox(height: 14),
                      _roundedDropdown(
                        value: golonganDarah,
                        items: ['A', 'B', 'O', 'AB'],
                        label: 'Golongan Darah',
                        icon: Icons.bloodtype,
                        onChanged: (v) => setState(() => golonganDarah = v),
                      ),
                      const SizedBox(height: 14),
                      _roundedDropdown(
                        value: statusPernikahan,
                        items: ['Menikah', 'Belum menikah', 'Janda/Duda'],
                        label: 'Status Pernikahan',
                        icon: Icons.family_restroom,
                        onChanged: (v) => setState(() => statusPernikahan = v),
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: alamatDomisiliController,
                        label: 'Alamat Lengkap Domisili',
                        icon: Icons.home,
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: alamatKtpController,
                        label: 'Alamat Lengkap Sesuai KTP',
                        icon: Icons.location_city,
                      ),
                      const SizedBox(height: 14),
                      GestureDetector(
                        onTap: () async {
                          final picked = await showDatePicker(
                            context: context,
                            initialDate: workStartDate ?? DateTime.now(),
                            firstDate: DateTime(2000),
                            lastDate: DateTime(2100),
                          );
                          if (picked != null) setState(() => workStartDate = picked);
                        },
                        child: AbsorbPointer(
                          child: _roundedInput(
                            controller: TextEditingController(
                              text: workStartDate == null ? '' : '${workStartDate!.year}-${workStartDate!.month.toString().padLeft(2, '0')}-${workStartDate!.day.toString().padLeft(2, '0')}',
                            ),
                            label: 'Tanggal Mulai Kerja',
                            icon: Icons.date_range,
                          ),
                        ),
                      ),
                      const SizedBox(height: 14),
                      const Text('Upload Foto Avatar (opsional)'),
                      Row(
                        children: [
                          ElevatedButton.icon(
                            onPressed: () async {
                              final picker = ImagePicker();
                              final picked = await picker.pickImage(source: ImageSource.gallery, imageQuality: 80);
                              if (picked != null) setState(() => avatarFile = File(picked.path));
                            },
                            icon: const Icon(Icons.upload_file),
                            label: const Text('Pilih File'),
                          ),
                          const SizedBox(width: 8),
                          if (avatarFile != null) Text('Sudah dipilih', style: TextStyle(color: Colors.green)),
                        ],
                      ),
                      const SizedBox(height: 24),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _roundedInput({required TextEditingController controller, required String label, required IconData icon}) {
    return TextFormField(
      controller: controller,
      style: GoogleFonts.poppins(fontSize: 15),
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: Colors.blue[400]),
        filled: true,
        fillColor: Colors.blue[50]?.withOpacity(0.2),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[400]!, width: 2),
        ),
      ),
      validator: (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
    );
  }

  Widget _roundedDropdown({required String? value, required List<String> items, required String label, required IconData icon, required void Function(String?) onChanged}) {
    return DropdownButtonFormField<String>(
      value: value,
      items: items.map((e) => DropdownMenuItem(value: e, child: Text(e, style: GoogleFonts.poppins()))).toList(),
      onChanged: onChanged,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: Colors.blue[400]),
        filled: true,
        fillColor: Colors.blue[50]?.withOpacity(0.2),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[400]!, width: 2),
        ),
      ),
      validator: (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
    );
  }

  Map<String, dynamic> getFormData() => {
    'nama': namaController.text,
    'noKtp': noKtpController.text,
    'jenisKelamin': jenisKelamin,
    'tempatLahir': tempatLahirController.text,
    'tanggalLahir': tanggalLahir,
    'agama': agama,
    'golonganDarah': golonganDarah,
    'statusPernikahan': statusPernikahan,
    'alamatDomisili': alamatDomisiliController.text,
    'alamatKtp': alamatKtpController.text,
    'idJabatan': selectedJabatanId,
    'workStartDate': workStartDate,
    'avatarFile': avatarFile,
  };
}

class _Step2KontakKeluarga extends StatefulWidget {
  final Map<String, dynamic> initialData;
  final GlobalKey<FormState> formKey;
  final void Function(Map<String, dynamic>) onNext;

  const _Step2KontakKeluarga({Key? key, required this.initialData, required this.formKey, required this.onNext}) : super(key: key);

  @override
  State<_Step2KontakKeluarga> createState() => _Step2KontakKeluargaState();
}

class _Step2KontakKeluargaState extends State<_Step2KontakKeluarga> with SingleTickerProviderStateMixin {
  final TextEditingController emailController = TextEditingController();
  final TextEditingController passwordController = TextEditingController();
  final TextEditingController confirmPasswordController = TextEditingController();
  final TextEditingController noHpController = TextEditingController();
  final TextEditingController namaKontakDaruratController = TextEditingController();
  String? hubunganKontakDarurat;
  final TextEditingController noHpKontakDaruratController = TextEditingController();
  final TextEditingController jumlahAnakController = TextEditingController();

  late AnimationController _animController;
  late Animation<double> _cardAnim;

  @override
  void initState() {
    super.initState();
    emailController.text = widget.initialData['email'] ?? '';
    passwordController.text = widget.initialData['password'] ?? '';
    confirmPasswordController.text = widget.initialData['confirmPassword'] ?? '';
    noHpController.text = widget.initialData['noHp'] ?? '';
    namaKontakDaruratController.text = widget.initialData['namaKontakDarurat'] ?? '';
    hubunganKontakDarurat = widget.initialData['hubunganKontakDarurat'];
    noHpKontakDaruratController.text = widget.initialData['noHpKontakDarurat'] ?? '';
    jumlahAnakController.text = widget.initialData['jumlahAnak']?.toString() ?? '';
    _animController = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _cardAnim = CurvedAnimation(parent: _animController, curve: Curves.easeOutBack);
    _animController.forward();
  }

  @override
  void dispose() {
    emailController.dispose();
    passwordController.dispose();
    confirmPasswordController.dispose();
    noHpController.dispose();
    namaKontakDaruratController.dispose();
    noHpKontakDaruratController.dispose();
    jumlahAnakController.dispose();
    _animController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      height: MediaQuery.of(context).size.height * 0.85,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFe3f0ff), Color(0xFFf8fbff), Color(0xFFe3f0ff)],
        ),
      ),
      child: Center(
        child: ScaleTransition(
          scale: _cardAnim,
          child: Card(
            elevation: 16,
            shadowColor: Colors.blue.withOpacity(0.2),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(32)),
            color: Colors.white.withOpacity(0.95),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
              child: SingleChildScrollView(
                padding: const EdgeInsets.only(bottom: 32),
                child: Form(
                  key: widget.formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Kontak & Keluarga',
                          style: GoogleFonts.poppins(
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                            color: Colors.blue[800],
                          )),
                      const SizedBox(height: 18),
                      _roundedInput(
                        controller: emailController,
                        label: 'Alamat Email',
                        icon: Icons.email,
                        keyboardType: TextInputType.emailAddress,
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: passwordController,
                        label: 'Password',
                        icon: Icons.lock,
                        obscureText: true,
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: confirmPasswordController,
                        label: 'Konfirmasi Password',
                        icon: Icons.lock_outline,
                        obscureText: true,
                        validator: (v) => v != passwordController.text ? 'Password tidak sama' : null,
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: noHpController,
                        label: 'Nomor Whatsapp/HP',
                        icon: Icons.phone,
                        keyboardType: TextInputType.phone,
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: namaKontakDaruratController,
                        label: 'Nama Kontak Darurat',
                        icon: Icons.person_pin,
                      ),
                      const SizedBox(height: 14),
                      _roundedDropdown(
                        value: hubunganKontakDarurat,
                        items: ['Suami/istri', 'Orang tua', 'Kakak/adik', 'Keluarga lainnya'],
                        label: 'Hubungan dengan Kontak Darurat',
                        icon: Icons.group,
                        onChanged: (v) => setState(() => hubunganKontakDarurat = v),
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: noHpKontakDaruratController,
                        label: 'Nomor Telepon Kontak Darurat',
                        icon: Icons.phone_in_talk,
                        keyboardType: TextInputType.phone,
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: jumlahAnakController,
                        label: 'Jumlah anak (bila tidak ada = 0)',
                        icon: Icons.child_care,
                        keyboardType: TextInputType.number,
                      ),
                      const SizedBox(height: 24),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _roundedInput({required TextEditingController controller, required String label, required IconData icon, TextInputType? keyboardType, bool obscureText = false, String? Function(String?)? validator}) {
    return TextFormField(
      controller: controller,
      style: GoogleFonts.poppins(fontSize: 15),
      keyboardType: keyboardType,
      obscureText: obscureText,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: Colors.blue[400]),
        filled: true,
        fillColor: Colors.blue[50]?.withOpacity(0.2),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[400]!, width: 2),
        ),
      ),
      validator: validator ?? (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
    );
  }

  Widget _roundedDropdown({required String? value, required List<String> items, required String label, required IconData icon, required void Function(String?) onChanged}) {
    return DropdownButtonFormField<String>(
      value: value,
      items: items.map((e) => DropdownMenuItem(value: e, child: Text(e, style: GoogleFonts.poppins()))).toList(),
      onChanged: onChanged,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: Colors.blue[400]),
        filled: true,
        fillColor: Colors.blue[50]?.withOpacity(0.2),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[400]!, width: 2),
        ),
      ),
      validator: (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
    );
  }

  Map<String, dynamic> getFormData() => {
    'email': emailController.text,
    'password': passwordController.text,
    'confirmPassword': confirmPasswordController.text,
    'noHp': noHpController.text,
    'namaKontakDarurat': namaKontakDaruratController.text,
    'hubunganKontakDarurat': hubunganKontakDarurat,
    'noHpKontakDarurat': noHpKontakDaruratController.text,
    'jumlahAnak': jumlahAnakController.text,
  };
}

class _Step3PendidikanPekerjaan extends StatefulWidget {
  final Map<String, dynamic> initialData;
  final GlobalKey<FormState> formKey;
  final void Function(Map<String, dynamic>) onNext;

  const _Step3PendidikanPekerjaan({Key? key, required this.initialData, required this.formKey, required this.onNext}) : super(key: key);

  @override
  State<_Step3PendidikanPekerjaan> createState() => _Step3PendidikanPekerjaanState();
}

class _Step3PendidikanPekerjaanState extends State<_Step3PendidikanPekerjaan> with SingleTickerProviderStateMixin {
  String? pendidikanTerakhir;
  final TextEditingController namaSekolahController = TextEditingController();
  final TextEditingController jurusanController = TextEditingController();

  late AnimationController _animController;
  late Animation<double> _cardAnim;

  @override
  void initState() {
    super.initState();
    pendidikanTerakhir = widget.initialData['pendidikanTerakhir'];
    namaSekolahController.text = widget.initialData['namaSekolah'] ?? '';
    jurusanController.text = widget.initialData['jurusan'] ?? '';
    _animController = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _cardAnim = CurvedAnimation(parent: _animController, curve: Curves.easeOutBack);
    _animController.forward();
  }

  @override
  void dispose() {
    namaSekolahController.dispose();
    jurusanController.dispose();
    _animController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      height: MediaQuery.of(context).size.height * 0.85,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFe3f0ff), Color(0xFFf8fbff), Color(0xFFe3f0ff)],
        ),
      ),
      child: Center(
        child: ScaleTransition(
          scale: _cardAnim,
          child: Card(
            elevation: 16,
            shadowColor: Colors.blue.withOpacity(0.2),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(32)),
            color: Colors.white.withOpacity(0.95),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
              child: SingleChildScrollView(
                padding: const EdgeInsets.only(bottom: 32),
                child: Form(
                  key: widget.formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Pendidikan & Pekerjaan',
                          style: GoogleFonts.poppins(
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                            color: Colors.blue[800],
                          )),
                      const SizedBox(height: 18),
                      _roundedDropdown(
                        value: pendidikanTerakhir,
                        items: ['SMA/SMK/sederajat', 'Diploma 1', 'Diploma 2', 'Diploma 3', 'S1', 'S2/S3'],
                        label: 'Pendidikan Terakhir',
                        icon: Icons.school,
                        onChanged: (v) => setState(() => pendidikanTerakhir = v),
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: namaSekolahController,
                        label: 'Nama sekolah/kampus',
                        icon: Icons.account_balance,
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: jurusanController,
                        label: 'Jurusan sekolah/kampus (terakhir)',
                        icon: Icons.menu_book,
                      ),
                      const SizedBox(height: 24),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _roundedInput({required TextEditingController controller, required String label, required IconData icon, TextInputType? keyboardType, bool obscureText = false, String? Function(String?)? validator}) {
    return TextFormField(
      controller: controller,
      style: GoogleFonts.poppins(fontSize: 15),
      keyboardType: keyboardType,
      obscureText: obscureText,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: Colors.blue[400]),
        filled: true,
        fillColor: Colors.blue[50]?.withOpacity(0.2),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[400]!, width: 2),
        ),
      ),
      validator: validator ?? (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
    );
  }

  Widget _roundedDropdown({required String? value, required List<String> items, required String label, required IconData icon, required void Function(String?) onChanged}) {
    return DropdownButtonFormField<String>(
      value: value,
      items: items.map((e) => DropdownMenuItem(value: e, child: Text(e, style: GoogleFonts.poppins()))).toList(),
      onChanged: onChanged,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: Colors.blue[400]),
        filled: true,
        fillColor: Colors.blue[50]?.withOpacity(0.2),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[400]!, width: 2),
        ),
      ),
      validator: (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
    );
  }

  Map<String, dynamic> getFormData() => {
    'pendidikanTerakhir': pendidikanTerakhir,
    'namaSekolah': namaSekolahController.text,
    'jurusan': jurusanController.text,
  };
}

class _Step4Dokumen extends StatefulWidget {
  final Map<String, dynamic> initialData;
  final GlobalKey<FormState> formKey;
  final void Function(Map<String, dynamic>) onNext;

  const _Step4Dokumen({Key? key, required this.initialData, required this.formKey, required this.onNext}) : super(key: key);

  @override
  State<_Step4Dokumen> createState() => _Step4DokumenState();
}

class _Step4DokumenState extends State<_Step4Dokumen> with SingleTickerProviderStateMixin {
  File? fotoKtp;
  File? fotoKk;
  File? fotoBerwarna;
  final TextEditingController noKkController = TextEditingController();
  final TextEditingController noRekeningController = TextEditingController();
  final TextEditingController namaRekeningController = TextEditingController();
  final TextEditingController npwpController = TextEditingController();
  final TextEditingController bpjsKesehatanController = TextEditingController();
  final TextEditingController bpjsKetenagakerjaanController = TextEditingController();

  late AnimationController _animController;
  late Animation<double> _cardAnim;

  @override
  void initState() {
    super.initState();
    fotoKtp = widget.initialData['fotoKtp'];
    fotoKk = widget.initialData['fotoKk'];
    fotoBerwarna = widget.initialData['fotoBerwarna'];
    noKkController.text = widget.initialData['noKk']?.toString() ?? '';
    noRekeningController.text = widget.initialData['noRekening']?.toString() ?? '';
    namaRekeningController.text = widget.initialData['namaRekening'] ?? '';
    npwpController.text = widget.initialData['npwp']?.toString() ?? '';
    bpjsKesehatanController.text = widget.initialData['bpjsKesehatan']?.toString() ?? '';
    bpjsKetenagakerjaanController.text = widget.initialData['bpjsKetenagakerjaan']?.toString() ?? '';
    _animController = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _cardAnim = CurvedAnimation(parent: _animController, curve: Curves.easeOutBack);
    _animController.forward();
  }

  Future<void> _pickImage(Function(File) onPicked) async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (picked != null) {
      onPicked(File(picked.path));
    }
  }

  @override
  void dispose() {
    fotoKtp = null;
    fotoKk = null;
    fotoBerwarna = null;
    noKkController.dispose();
    noRekeningController.dispose();
    namaRekeningController.dispose();
    npwpController.dispose();
    bpjsKesehatanController.dispose();
    bpjsKetenagakerjaanController.dispose();
    _animController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      height: MediaQuery.of(context).size.height * 0.85,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFe3f0ff), Color(0xFFf8fbff), Color(0xFFe3f0ff)],
        ),
      ),
      child: Center(
        child: ScaleTransition(
          scale: _cardAnim,
          child: Card(
            elevation: 16,
            shadowColor: Colors.blue.withOpacity(0.2),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(32)),
            color: Colors.white.withOpacity(0.95),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
              child: SingleChildScrollView(
                padding: const EdgeInsets.only(bottom: 32),
                child: Form(
                  key: widget.formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Dokumen',
                          style: GoogleFonts.poppins(
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                            color: Colors.blue[800],
                          )),
                      const SizedBox(height: 18),
                      const Text('Upload Foto KTP *'),
                      Row(
                        children: [
                          ElevatedButton.icon(
                            onPressed: () => _pickImage((f) => setState(() => fotoKtp = f)),
                            icon: const Icon(Icons.upload_file),
                            label: const Text('Pilih File'),
                          ),
                          const SizedBox(width: 8),
                          if (fotoKtp != null) Text('Sudah dipilih', style: TextStyle(color: Colors.green[700])),
                        ],
                      ),
                      const SizedBox(height: 14),
                      const Text('Upload Foto KK *'),
                      Row(
                        children: [
                          ElevatedButton.icon(
                            onPressed: () => _pickImage((f) => setState(() => fotoKk = f)),
                            icon: const Icon(Icons.upload_file),
                            label: const Text('Pilih File'),
                          ),
                          const SizedBox(width: 8),
                          if (fotoKk != null) Text('Sudah dipilih', style: TextStyle(color: Colors.green[700])),
                        ],
                      ),
                      const SizedBox(height: 14),
                      const Text('Upload Foto Berwarna Terbaru *'),
                      Row(
                        children: [
                          ElevatedButton.icon(
                            onPressed: () => _pickImage((f) => setState(() => fotoBerwarna = f)),
                            icon: const Icon(Icons.upload_file),
                            label: const Text('Pilih File'),
                          ),
                          const SizedBox(width: 8),
                          if (fotoBerwarna != null) Text('Sudah dipilih', style: TextStyle(color: Colors.green[700])),
                        ],
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: noKkController,
                        label: 'Nomor KK',
                        icon: Icons.numbers,
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: noRekeningController,
                        label: 'Nomor Rekening BCA (bila tidak ada = 0)',
                        icon: Icons.account_balance,
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: namaRekeningController,
                        label: 'Nama Sesuai Rekening',
                        icon: Icons.account_circle,
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: npwpController,
                        label: 'Nomor NPWP (bila tidak ada = 0)',
                        icon: Icons.numbers,
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: bpjsKesehatanController,
                        label: 'Nomor BPJS Kesehatan (bila tidak ada = 0)',
                        icon: Icons.health_and_safety,
                      ),
                      const SizedBox(height: 14),
                      _roundedInput(
                        controller: bpjsKetenagakerjaanController,
                        label: 'Nomor BPJS Tenaga Kerja (bila tidak ada = 0)',
                        icon: Icons.health_and_safety_outlined,
                      ),
                      const SizedBox(height: 24),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _roundedInput({required TextEditingController controller, required String label, required IconData icon, TextInputType? keyboardType, bool obscureText = false, String? Function(String?)? validator}) {
    return TextFormField(
      controller: controller,
      style: GoogleFonts.poppins(fontSize: 15),
      keyboardType: keyboardType,
      obscureText: obscureText,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: Colors.blue[400]),
        filled: true,
        fillColor: Colors.blue[50]?.withOpacity(0.2),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[100]!, width: 1.2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: Colors.blue[400]!, width: 2),
        ),
      ),
      validator: validator ?? (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
    );
  }

  Map<String, dynamic> getFormData() => {
    'fotoKtp': fotoKtp,
    'fotoKk': fotoKk,
    'fotoBerwarna': fotoBerwarna,
    'noKk': noKkController.text,
    'noRekening': noRekeningController.text,
    'namaRekening': namaRekeningController.text,
    'npwp': npwpController.text,
    'bpjsKesehatan': bpjsKesehatanController.text,
    'bpjsKetenagakerjaan': bpjsKetenagakerjaanController.text,
  };
}

class _Step5ReviewSubmit extends StatelessWidget {
  final Map<String, dynamic> formData;
  final Function() onSubmit;
  final GlobalKey<FormState> formKey;

  const _Step5ReviewSubmit({Key? key, required this.formData, required this.onSubmit, required this.formKey}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Review Data Registrasi', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          const SizedBox(height: 16),
          _reviewItem('Nama Lengkap', formData['nama'] ?? ''),
          _reviewItem('No KTP', formData['noKtp'] ?? ''),
          _reviewItem('Jenis Kelamin', formData['jenisKelamin'] ?? ''),
          _reviewItem('Tempat Lahir', formData['tempatLahir'] ?? ''),
          _reviewItem('Tanggal Lahir', formData['tanggalLahir']?.toString() ?? ''),
          _reviewItem('Agama', formData['agama'] ?? ''),
          _reviewItem('Golongan Darah', formData['golonganDarah'] ?? ''),
          _reviewItem('Status Pernikahan', formData['statusPernikahan'] ?? ''),
          _reviewItem('Alamat Domisili', formData['alamatDomisili'] ?? ''),
          _reviewItem('Alamat KTP', formData['alamatKtp'] ?? ''),
          _reviewItem('Email', formData['email'] ?? ''),
          _reviewItem('No HP', formData['noHp'] ?? ''),
          _reviewItem('Nama Kontak Darurat', formData['namaKontakDarurat'] ?? ''),
          _reviewItem('Hubungan Kontak Darurat', formData['hubunganKontakDarurat'] ?? ''),
          _reviewItem('No HP Kontak Darurat', formData['noHpKontakDarurat'] ?? ''),
          _reviewItem('Jumlah Anak', formData['jumlahAnak'] ?? ''),
          _reviewItem('Pendidikan Terakhir', formData['pendidikanTerakhir'] ?? ''),
          _reviewItem('Nama Sekolah/Kampus', formData['namaSekolah'] ?? ''),
          _reviewItem('Jurusan', formData['jurusan'] ?? ''),
          _reviewItem('No Rekening', formData['noRekening'] ?? ''),
          _reviewItem('Nama Rekening', formData['namaRekening'] ?? ''),
          _reviewItem('NPWP', formData['npwp'] ?? ''),
          _reviewItem('BPJS Kesehatan', formData['bpjsKesehatan'] ?? ''),
          _reviewItem('BPJS Tenaga Kerja', formData['bpjsKetenagakerjaan'] ?? ''),
          const SizedBox(height: 12),
          const Text('File Upload:', style: TextStyle(fontWeight: FontWeight.bold)),
          _fileReview('Foto KTP', formData['fotoKtp']),
          _fileReview('Foto KK', formData['fotoKk']),
          _fileReview('Foto Berwarna', formData['fotoBerwarna']),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: onSubmit,
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.green[700],
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: const Text('Kirim Registrasi', style: TextStyle(fontSize: 16)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _reviewItem(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 160, child: Text(label, style: const TextStyle(fontWeight: FontWeight.w500))),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }

  Widget _fileReview(String label, dynamic file) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          SizedBox(width: 160, child: Text(label)),
          Expanded(child: Text(file != null ? 'Sudah dipilih' : '-')),
        ],
      ),
    );
  }
} 