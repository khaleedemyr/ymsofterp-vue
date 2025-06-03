import 'package:flutter/material.dart';

class RegisterPrepPage extends StatelessWidget {
  const RegisterPrepPage({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final dokumenList = [
      'Foto KTP (max 1MB)',
      'Foto Kartu Keluarga (max 1MB)',
      'Foto Berwarna Terbaru (max 10MB)',
      'Nomor KTP & KK',
      'Nomor Rekening BCA (jika ada)',
      'Nomor BPJS Kesehatan & Ketenagakerjaan (jika ada)',
      'NPWP (jika ada)',
      'Data pendidikan terakhir',
      'Data kontak darurat',
    ];
    return Scaffold(
      appBar: AppBar(
        title: const Text('Persiapan Registrasi'),
        backgroundColor: Colors.blue[700],
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Sebelum registrasi, siapkan dokumen dan data berikut:',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 18),
            ...dokumenList.map((d) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(
                    children: [
                      const Icon(Icons.check_circle_outline, color: Colors.blue, size: 22),
                      const SizedBox(width: 10),
                      Expanded(child: Text(d, style: const TextStyle(fontSize: 15))),
                    ],
                  ),
                )),
            const Spacer(),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.of(context).pushReplacementNamed('/register');
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.blue[700],
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text('Mulai Registrasi', style: TextStyle(fontSize: 16, color: Colors.white)),
              ),
            ),
          ],
        ),
      ),
    );
  }
} 