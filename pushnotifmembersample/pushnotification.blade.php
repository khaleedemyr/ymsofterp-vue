@extends('layouts_admin.app')

@section('content')


    <!DOCTYPE html>
    <html lang="en-us">

    @include ('layouts_admin.head')

    <body class="desktop-detected pace-done smart-style-6">

        <!-- HEADER -->
        @include ('layouts_admin.header')
        <!-- END HEADER -->

        <!-- Left panel : Navigation area -->
        <!-- Note: This width of the aside area can be adjusted through LESS variables -->
        @include ('layouts_admin.menu')
        <!-- END NAVIGATION -->

        <!-- MAIN PANEL -->
        <div id="main" role="main">

            <!-- RIBBON -->
            <div id="ribbon">

                <span class="ribbon-button-alignment">
                    <span id="refresh" class="btn btn-ribbon" data-action="resetWidgets" data-title="refresh" rel="tooltip"
                        data-placement="bottom"
                        data-original-title="<i class='text-warning fa fa-warning'></i> Warning! This will reset all your widget settings."
                        data-html="true">
                        <i class="fa fa-refresh"></i>
                    </span>
                </span>

                <!-- breadcrumb -->
                <ol class="breadcrumb">
                    <li>Home</li>
                    <li>Data Push Notifikasi</li>
                </ol>
                <!-- end breadcrumb -->

                <!-- You can also add more buttons to the
                                                ribbon for further usability

                                                Example below:

                                                <span class="ribbon-button-alignment pull-right">
                                                <span id="search" class="btn btn-ribbon hidden-xs" data-title="search"><i class="fa-grid"></i> Change Grid</span>
                                                <span id="add" class="btn btn-ribbon hidden-xs" data-title="add"><i class="fa-plus"></i> Add</span>
                                                <span id="search" class="btn btn-ribbon" data-title="search"><i class="fa-search"></i> <span class="hidden-mobile">Search</span></span>
                                                </span> -->

            </div>
            <!-- END RIBBON -->

            <!-- MAIN CONTENT -->
            <div id="content">

                <div class="row">
                    <div class="col-xs-12 col-sm-7 col-md-7 col-lg-4">
                        <h1 class="page-title txt-color-blueDark">
                            <i class="fa fa-table fa-fw "></i>
                            Table
                            <span>>
                                Data Push Notifikasi
                            </span>
                        </h1>
                    </div>
                </div>

                @if (session('msg'))
                    <div class="alert alert-block alert-success">
                        <a class="close" data-dismiss="alert" href="#">×</a>
                        <h4 class="alert-heading">Sukses!</h4>
                        {{ session('msg') }}<br>
                    </div>
                @endif

                @if (session('pesanError'))
                    <div class="alert alert-danger alert-block">
                        <a class="close" data-dismiss="alert" href="#">×</a>
                        <h4 class="alert-heading">Error!</h4>
                        @foreach (session('pesanError') as $indeks => $pesan_tampil)
                            {{ $indeks . '. ' . $pesan_tampil }}<br>
                        @endforeach
                    </div>
                @endif

                @if (session('pesanDeleteError'))
                    <div class="alert alert-danger alert-block">
                        <a class="close" data-dismiss="alert" href="#">×</a>
                        <h4 class="alert-heading">Error!</h4>
                        @foreach (session('pesanDeleteError') as $indeks => $pesan_tampil)
                            {{ $indeks . '. ' . $pesan_tampil }}<br>
                        @endforeach
                    </div>
                @endif

                @if (session('pesanUpdateError'))
                    <div class="alert alert-danger alert-block">
                        <a class="close" data-dismiss="alert" href="#">×</a>
                        <h4 class="alert-heading">Error!</h4>
                        @foreach (session('pesanUpdateError') as $indeks => $pesan_tampil)
                            {{ $indeks . '. ' . $pesan_tampil }}<br>
                        @endforeach
                    </div>
                @endif

                <!-- widget grid -->
                <section id="widget-grid" class="">

                    <!-- row -->
                    <div class="row">


                        <article class="col-xs-12 col-sm-12 col-md-12 col-lg-12">

                            <!-- Widget ID (each widget will need unique ID)-->
                            <div class="jarviswidget jarviswidget-color-darken" id="wid-id-0"
                                data-widget-editbutton="false">
                                <header>
                                    <h2><i class="fa fa-pencil"></i> New Push Notification</h2>
                                </header>

                                <!-- widget div-->
                                <div>

                                    <!-- widget edit box -->
                                    <div class="jarviswidget-editbox">
                                        <!-- This area used as dropdown edit box -->

                                    </div>
                                    <!-- end widget edit box -->

                                    <!-- widget content -->
                                    <div class="widget-body no-padding">

                                        <form id="checkout-form" class="smart-form" method="POST"
                                            action="{{ url('/admin/pushnotification/insert/input') }}"
                                            novalidate="novalidate" enctype="multipart/form-data">
                                            {{ csrf_field() }}
                                            <fieldset>
                                                <div class="row">
                                                    <section class="col col-6">
                                                        <label class="input"> <i
                                                                class="icon-prepend fa fa-lg fa-fw fa-coffee"></i>
                                                            <input type="text" name="txt_target"
                                                                placeholder="Target Email Member" value="" required>
                                                        </label>
                                                        <small>Tambahkan koma untuk multi target, ketik "all" untuk blast ke
                                                            semua Devices.</small>
                                                    </section>
                                                </div>
                                                <div class="row">
                                                    <section class="col col-6">
                                                        <label class="input"> <i
                                                                class="icon-prepend fa fa-lg fa-fw fa-coffee"></i>
                                                            <input type="text" name="txt_title" placeholder="Title"
                                                                value="" required>
                                                        </label>
                                                    </section>
                                                </div>
                                                <div class="row">
                                                    <section class="col col-6">
                                                        <label class="input"> <i
                                                                class="icon-prepend fa fa-lg fa-fw fa-coffee"></i>
                                                            <input type="text" name="txt_body" placeholder="Body"
                                                                value="" required>
                                                        </label>
                                                    </section>
                                                </div>
                                                <div class="row">
                                                    <section class="col col-6">
                                                        <div class="input input-file">
                                                            <span class="button"><input id="file2" type="file"
                                                                    name="file_foto"
                                                                    onchange="this.parentNode.nextSibling.value = this.value">Cari</span><input
                                                                type="text" placeholder="Gambar" readonly="">(Best
                                                            Resolution 1024x500 px)
                                                        </div>
                                                    </section>
                                                </div>
                                            </fieldset>

                                            <footer>
                                                <button type="submit" class="btn btn-primary pull-left">
                                                    Send
                                                </button>
                                            </footer>
                                        </form>

                                    </div>
                                    <!-- end widget content -->

                                </div>
                                <!-- end widget div -->

                            </div>
                            <!-- end widget -->

                        </article>
                        <!-- WIDGET END -->

                        <!-- NEW WIDGET START -->
                        <article class="col-xs-12 col-sm-12 col-md-12 col-lg-12">

                            <!-- Widget ID (each widget will need unique ID)-->
                            <div class="jarviswidget jarviswidget-color-darken" id="wid-id-0"
                                data-widget-editbutton="false">
                                <header>
                                    <h2><i class="fa fa-database"></i> Data Push Notifikasi | Total Devices :
                                        {{ $totalDevices }}</h2>
                                </header>

                                <!-- widget div-->
                                <div>

                                    <!-- widget edit box -->
                                    <div class="jarviswidget-editbox">
                                        <!-- This area used as dropdown edit box -->

                                    </div>
                                    <!-- end widget edit box -->

                                    <!-- widget content -->
                                    <div class="widget-body no-padding">

                                        <table id="dt_basic" class="table table-striped table-bordered table-hover"
                                            width="100%">
                                            <thead>
                                                <tr>
                                                    <!--													<th data-hide="phone">ID</th> -->
                                                    <th>Id</th>
                                                    <th data-class="expand"><i
                                                            class="fa fa-fw fa-user text-muted hidden-md hidden-sm hidden-xs"></i>
                                                        Title</th>
                                                    <th data-class="expand"><i
                                                            class="fa fa-fw fa-user text-muted hidden-md hidden-sm hidden-xs"></i>
                                                        Body</th>
                                                    <th data-class="expand">Target</th>
                                                    <th data-class="expand">Status</th>
                                                    <th data-hide="phone,tablet"><i class=""></i> Image</th>
                                                    <th data-hide="phone,tablet"><i class=""></i> Report</th>
                                                    <!--																										<th data-hide="phone,tablet"><i class=""></i> </th> -->
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($dataNotifications as $rowData)
                                                    <tr>
                                                        <td>{{ $rowData->id }}</td>
                                                        <td>{{ $rowData->title }}</td>
                                                        <td>{{ $rowData->body }}</td>
                                                        <td>{{ $rowData->target }}</td>
                                                        <td>
                                                            @php
                                                                $statusMessages = [
                                                                    0 => 'belum terkirim',
                                                                    1 => 'terkirim',
                                                                    2 => 'sedang di proses',
                                                                ];
                                                            @endphp
                                                            {{ $statusMessages[$rowData->status_send] ?? 'status tidak diketahui' }}
                                                        </td>
                                                        <td>
                                                            @php
                                                                $filePath = public_path(
                                                                    'assets/file_photo_notification/' . $rowData->photo,
                                                                );
                                                            @endphp

                                                            @if (file_exists($filePath) && !is_dir($filePath) && getimagesize($filePath))
                                                                <a href="{{ asset('assets/file_photo_notification/' . $rowData->photo) }}"
                                                                    target="_blank">[image]</a>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if ($rowData->target == 'all' || $rowData->target == '')
                                                                {{ $rowData->status_send == 1 ? 'Finish' : 'Onprocess' }} :
                                                                Terkirim notifikasi ke {{ $rowData->sended_devices }}
                                                                perangkat
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>

                                    </div>
                                    <!-- end widget content -->

                                </div>
                                <!-- end widget div -->

                            </div>
                            <!-- end widget -->

                        </article>
                        <!-- WIDGET END -->
                        <!-- NEW WIDGET START -->
                        <article class="col-xs-12 col-sm-12 col-md-12 col-lg-12">

                            <!-- Widget ID (each widget will need unique ID)-->
                            <div class="jarviswidget jarviswidget-color-darken" id="wid-id-3"
                                data-widget-editbutton="false">
                                <header>
                                    <h2><i class="fa fa-database"></i> Data Target Promo 1 s/d 8 Agustus</h2>
                                </header>
                                <!-- widget div-->
                                <div>
                                    <!-- widget edit box -->
                                    <div class="jarviswidget-editbox">
                                        <!-- This area used as dropdown edit box -->
                                    </div>
                                    <div class="widget-body no-padding">
                                        <table id="dt_basic2" class="table table-striped table-bordered table-hover"
                                            width="100%">
                                            <thead>
                                                <tr>
                                                    <th data-class="expand">Nomor</th>
                                                    <th data-class="expand">Tanggal</th>
                                                    <th data-class="expand">Nama Barang</th>
                                                    <th data-class="expand">Qty</th>
                                                    <th data-class="expand">Email</th>
                                                    <th data-class="expand">Terkirim</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($promoSatuAgustus as $rowData)
                                                    @if ($rowData->Email != '')
                                                        {{-- && $rowData->TelahTerkirim == "0" --}}
                                                        <tr>
                                                            <td>{{ $rowData->Nomor }}</td>
                                                            <td>{{ $rowData->Tanggal }}</td>
                                                            <td>{{ $rowData->NamaBarang }}</td>
                                                            <td>{{ $rowData->Qty }}</td>
                                                            <td>{{ $rowData->Email }}</td>
                                                            <td>{{ $rowData->TelahTerkirim == '1' ? 'Sudah' : 'Belum' }}
                                                            </td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <!-- end widget content -->
                                </div>
                                <!-- end widget div -->
                            </div>
                            <!-- end widget -->
                        </article>
                        <!-- WIDGET END -->

                        <!-- NEW WIDGET START -->
                        <article class="col-xs-12 col-sm-12 col-md-12 col-lg-12">

                            <!-- Widget ID (each widget will need unique ID)-->
                            <div class="jarviswidget jarviswidget-color-darken" id="wid-id-4"
                                data-widget-editbutton="false">
                                <header>
                                    <h2><i class="fa fa-database"></i> Data Target Promo 8 Agustus s/d 8 September</h2>
                                </header>
                                <!-- widget div-->
                                <div>
                                    <div class="jarviswidget-editbox">
                                    </div>
                                    <div class="widget-body no-padding">
                                        <table id="dt_basic2" class="table table-striped table-bordered table-hover"
                                            width="100%">
                                            <thead>
                                                <tr>
                                                    <th data-class="expand">Tgl Transaksi</th>
                                                    <th data-class="expand">Nomor</th>
                                                    <th data-class="expand">Id Costumer</th>
                                                    <th data-class="expand">Jumlah Transaksi</th>
                                                    <th data-class="expand">Email</th>
                                                    <th data-class="expand">Terkirim</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($viewMemberPoints as $rowData)
                                                    @if ($rowData->Email != '')
                                                        {{-- && $rowData->TelahTerkirim == "0" --}}
                                                        <tr>
                                                            <td>{{ $rowData->created_at }}</td>
                                                            <td>{{ $rowData->no_bill }}</td>
                                                            <td>{{ $rowData->costumer_id }}</td>
                                                            <td>{{ $rowData->jml_trans }}</td>
                                                            <td>{{ @$rowData->Email }}</td>
                                                            <td>{{ @$rowData->TelahTerkirim == '1' ? 'Sudah' : 'Belum' }}
                                                            </td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <!-- end widget content -->
                                </div>
                                <!-- end widget div -->
                            </div>
                            <!-- end widget -->
                        </article>
                        <!-- WIDGET END -->
                    </div>
                    <div class="row">
                        <div class="col-12 text-break">
                            @php
                                // Inisialisasi array kosong untuk menyimpan email
                                $emailList = [];
                            @endphp

                            @foreach ($viewMemberPoints as $rowData)
                                @if ($rowData->Email != '' && $rowData->TelahTerkirim == '0')
                                    {{-- Tambahkan email ke dalam array jika memenuhi kondisi --}}
                                    @php
                                        $emailList[] = $rowData->Email;
                                    @endphp
                                @endif
                            @endforeach
                            @php
                                $limitedEmailList = array_slice($emailList, 0, 500);
                            @endphp
                            {{-- Gabungkan email menjadi satu string dengan pemisah koma --}}
                            
                            <div class="p-3 border" style="max-width: 100%; word-wrap: break-word; white-space: pre-wrap;">
                                {{ implode(',', $limitedEmailList) }}
                            </div>
                
                        </div>
                    </div>
                    <!-- end row -->
                    <!-- end row -->
                </section>
                <!-- end widget grid -->
            </div>
            <!-- END MAIN CONTENT -->
        </div>
        <!-- END MAIN PANEL -->
        <!-- PAGE FOOTER -->
        <div class="page-footer">
            <div class="row">
                <div class="col-xs-12 col-sm-6">
                    <span class="txt-color-white">SmartAdmin 1.9.0 <span class="hidden-xs"> - Web Application
                            Framework</span> © 2017-2019</span>
                </div>

                <div class="col-xs-6 col-sm-6 text-right hidden-xs">
                    <div class="txt-color-white inline-block">
                        <i class="txt-color-blueLight hidden-mobile">Last account activity <i class="fa fa-clock-o"></i>
                            <strong>52 mins ago &nbsp;</strong> </i>
                        <div class="btn-group dropup">
                            <button class="btn btn-xs dropdown-toggle bg-color-blue txt-color-white"
                                data-toggle="dropdown">
                                <i class="fa fa-link"></i> <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu pull-right text-left">
                                <li>
                                    <div class="padding-5">
                                        <p class="txt-color-darken font-sm no-margin">Download Progress</p>
                                        <div class="progress progress-micro no-margin">
                                            <div class="progress-bar progress-bar-success" style="width: 50%;"></div>
                                        </div>
                                    </div>
                                </li>
                                <li class="divider"></li>
                                <li>
                                    <div class="padding-5">
                                        <p class="txt-color-darken font-sm no-margin">Server Load</p>
                                        <div class="progress progress-micro no-margin">
                                            <div class="progress-bar progress-bar-success" style="width: 20%;"></div>
                                        </div>
                                    </div>
                                </li>
                                <li class="divider"></li>
                                <li>
                                    <div class="padding-5">
                                        <p class="txt-color-darken font-sm no-margin">Memory Load <span
                                                class="text-danger">*critical*</span></p>
                                        <div class="progress progress-micro no-margin">
                                            <div class="progress-bar progress-bar-danger" style="width: 70%;"></div>
                                        </div>
                                    </div>
                                </li>
                                <li class="divider"></li>
                                <li>
                                    <div class="padding-5">
                                        <button class="btn btn-block btn-default">refresh</button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END PAGE FOOTER -->

        <!-- SHORTCUT AREA : With large tiles (activated via clicking user name tag)
                                              Note: These tiles are completely responsive,
                                              you can add as many as you like
                                              -->
        <div id="shortcut">
            <ul>
                <li>
                    <a href="inbox.html" class="jarvismetro-tile big-cubes bg-color-blue"> <span class="iconbox"> <i
                                class="fa fa-envelope fa-4x"></i> <span>Mail <span
                                    class="label pull-right bg-color-darken">14</span></span> </span> </a>
                </li>
                <li>
                    <a href="calendar.html" class="jarvismetro-tile big-cubes bg-color-orangeDark"> <span
                            class="iconbox"> <i class="fa fa-calendar fa-4x"></i> <span>Calendar</span> </span> </a>
                </li>
                <li>
                    <a href="gmap-xml.html" class="jarvismetro-tile big-cubes bg-color-purple"> <span class="iconbox"> <i
                                class="fa fa-map-marker fa-4x"></i> <span>Maps</span> </span> </a>
                </li>
                <li>
                    <a href="invoice.html" class="jarvismetro-tile big-cubes bg-color-blueDark"> <span class="iconbox">
                            <i class="fa fa-book fa-4x"></i> <span>Invoice <span
                                    class="label pull-right bg-color-darken">99</span></span> </span> </a>
                </li>
                <li>
                    <a href="gallery.html" class="jarvismetro-tile big-cubes bg-color-greenLight"> <span class="iconbox">
                            <i class="fa fa-picture-o fa-4x"></i> <span>Gallery </span> </span> </a>
                </li>
                <li>
                    <a href="profile.html" class="jarvismetro-tile big-cubes selected bg-color-pinkDark"> <span
                            class="iconbox"> <i class="fa fa-user fa-4x"></i> <span>My Profile </span> </span> </a>
                </li>
            </ul>
        </div>
        <!-- END SHORTCUT AREA -->

        <!--================================================== -->

        <!-- PACE LOADER - turn this on if you want ajax loading to show (caution: uses lots of memory on iDevices)-->
        <!-- PACE LOADER - turn this on if you want ajax loading to show (caution: uses lots of memory on iDevices)-->

        @include ('layouts_admin.js_admin.js_users')

    </body>

    </html>


@endsection
