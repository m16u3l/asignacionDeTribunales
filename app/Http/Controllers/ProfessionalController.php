<?php

namespace App\Http\Controllers;

use Exception;
use Excel;
use Validator;
use Redirect;

// Models
use App\Area;
use App\Contact;
use App\Degree;
use App\Professional;
use App\Court;
use App\Profile;
use App\State;
use App\Date;
use App\RejectionRequest;
// libs

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Input;
use Illuminate\Database\QueryException;

class ProfessionalController extends Controller
{

  public function professional_list(Request $request){
    $degrees = Degree::all();

    $professionals = Professional::orderBy('name')
    ->search_by_name($request->name)
    ->paginate(10);
    return view('professional.professional_list', compact('professionals', 'degrees'));
  }

  public function index(Request $request, $id)
  {
    $url = '/registrar_tribunal';
    $profile = Profile::find($id);
    $area = $profile->areas->first();
    $courts=DB::table('professionals')
    ->join('courts', 'professionals.id', '=', 'courts.professional_id')
    ->select('professionals.*')
    ->where('courts.profile_id', '=', $profile->id)
    ->orderBy('count')
    ->get();
    $professionals = DB::table('professionals')
    ->join('area_interests', 'professionals.id', '=', 'area_interests.professional_id')
    ->select('professionals.*')
    ->where('area_interests.area_id', '=', $area->id)
    ->whereNotIn('professionals.id', DB::table('professionals')
    ->join('tutors', 'professionals.id', '=', 'tutors.professional_id')
    ->select('professionals.id')
    ->where('tutors.profile_id', '=', $profile->id))
    ->whereNotIn('professionals.id', DB::table('professionals')
    ->join('courts','professionals.id', '=', 'courts.professional_id')
    ->select('professionals.id')
    ->where('courts.profile_id', '=', $profile->id))
    ->orderBy('count')
    ->get();
    $allProfessionals = Professional::whereNotIn('professionals.id', DB::table('professionals')
    ->join('tutors', 'professionals.id', '=', 'tutors.professional_id')
    ->select('professionals.id')
    ->where('tutors.profile_id', '=', $profile->id))
    ->whereNotIn('professionals.id', DB::table('professionals')
    ->join('area_interests','professionals.id', '=', 'area_interests.professional_id')
    ->select('professionals.id')
    ->where('area_interests.area_id', '=', $area->id))
    ->whereNotIn('professionals.id', DB::table('professionals')
    ->join('courts','professionals.id', '=', 'courts.professional_id')
    ->select('professionals.id')
    ->where('courts.profile_id', '=', $profile->id))
    ->orderBy('count')
    ->get();
    return view('professional.assign_professinal', compact('url','profile','courts', 'professionals','allProfessionals'));
  }

  public function store_rejection_request(Request $request)
  {
    $profile_id = $request->profile_id;
    $professional_id = $request->professional_id;
    $description=$request->description;

    $now = new \DateTime();
    $profile = Profile::find($profile_id);
    $profile->courts()->detach($professional_id);
    $state = State::where('name','approved')->first();

    DB::table('profiles')->where('id', $profile_id)->decrement('count');
    $rejection_request = new RejectionRequest;
    $rejection_request->description=$description;
    $rejection_request->professional_id=$professional_id;
    $rejection_request->profile_id=$profile_id;
    $rejection_request->date=$now;
    $rejection_request->save();
    return redirect('/perfiles/'. $profile_id);
  }

  public function store(Request $request)
  {
    $now = new \DateTime();
    $url = 'perfiles/';
    $profile_id = $request->profile_id;
    $professional_id = $request->professional_id;
    $state = State::where('name','assigned')->first();
    $profile=Profile::find($request->profile_id);
    $profile->state_id=$state->id;
    $profile->save();
    $dates = Date::where('profile_id','=',$profile_id)->first();
    $dates->assigned = $now;
    $dates->save();
    $court = new Court;
    $court->profile_id = $profile_id;
    $court->professional_id = $professional_id;
    $court->assigned = $now;
    $court->save();
    DB::table('profiles')->where('id', $profile_id)->increment('count');
    DB::table('professionals')->where('id', $professional_id)->increment('count');
    return redirect($url);
  }


  public function create(Request $request){
    try {
      $new_professional = new Professional;
      $new_professional->ci = $request->ci;
      $new_professional->cod_sis = $request->cod_sis;
      $new_professional->name = $request->name;
      $new_professional->last_name_father = $request->last_name_father;
      $new_professional->last_name_mother = $request->last_name_mother;
      $new_professional->workload = $request->workload;
      $new_professional->degree_id = $request->degree;
      $new_professional->save();

      $contact = new Contact;
      $contact->email = $request->email;
      $contact->phone = $request->phone;
      $contact->address = $request->address;
      $contact->professional_id = $new_professional->id;
      $contact->save();
      $response = array("name"=>$request->name, "status"=>true);
    }
    catch (QueryException $e){
      return response()->json(array("status"=>false));
    }
    return response()->json($response);
  }



  public function update(Request $request){
    try{
      $professional_update = Professional::find($request->id);
      $professional_update->ci = $request->ci;
      $professional_update->cod_sis = $request->cod_sis;
      $professional_update->name = $request->name;
      $professional_update->last_name_father = $request->last_name_father;
      $professional_update->last_name_mother = $request->last_name_mother;
      $professional_update->workload = $request->workload;
      $professional_update->degree_id = $request->degree;
      $professional_update->save();

      $contact = Contact::where('professional_id', $request->id)->first();

      $contact->email = $request->email;
      $contact->phone = $request->phone;
      $contact->address = $request->address;
      $contact->professional_id  = $professional_update->id;
      $contact->save();

      $response = array("name"=>$request->name, "status"=>true);
    }
    catch (QueryException $e){
      return response()->json(array("status"=>false));
    }
    return response()->json($response);
  }
  // End Andres

  public function upload_professionals($value='')
  {
    $messages = null;
    return view('import.import_professionals', compact('messages'));
  }

  public function destroy($id)
  {
    //
  }

  public function uploadProfessionals($value='')
  {
    $messages = null;
    return view('import.import_professionals', compact('messages'));
  }

  public function import_professionals(Request $request)
  {
    $file = Input::file('fileProfessionals');
    $rules = array(
      'fileProfessionals' => 'required|mimes:xlsx',
    );
    $messages = array(
      'required' => 'ningun archivo xlsx seleccionado',
      'mimes' => 'el archivo debe estar en formato .xlsx'
    );

    $validator = Validator::make(Input::all(), $rules, $messages);
    if ($validator->fails()) {
      return redirect('import_professionals')->withErrors($validator);

    } else if(!$this->valid_document($file)) {

      return redirect('import_professionals')->with('bad_status', 'Documento invalido');

    } else if($validator->passes()) {
      Excel::load($file, function($reader)
      {
        foreach ($reader->get() as $key => $value) {
          $prof = Professional::where('ci', $value->ci)->first();
          if(is_null($prof)) {
            if (!is_null($value->nombre) &&
            !is_null($value->apellido_materno) &&
            !is_null($value->apellido_paterno)) {

              $degree = Degree::where('acronym', $value->titulo_docente)->first();

              if(is_null($degree)) {
                $degree = new Degree;
                $degree->acronym = $value->titulo_docente;
                $degree->save();
              }

              $professional = new Professional;
              $professional->name = $value->nombre;
              $professional->last_name_mother = $value->apellido_materno;
              $professional->last_name_father = $value->apellido_paterno;
              $professional->ci = $value->ci;
              $professional->cod_sis = $value->cod_sis;
              $professional->workload = $value->carga_horaria;
              $professional->degree_id = $degree->id;
              $professional->save();

              $contact = new Contact;
              $contact->email = $value->correo;
              $contact->phone = $value->telefono;
              $contact->address = $value->direccion;
              $contact->profile = $value->perfil;
              $contact->professional_id  = $professional->id;
              $contact->save();
            }
          }
        }
      });
    }
    return redirect('import_professionals')->with('status', 'Los cambios se realizaron con exito.');
  }

  public function valid_document($file)
  {
    $valid = False;
    Excel::load($file, function($file) use (&$valid){
      $rs = $file->get();
      $row = $rs[0];
      $headers = $row->keys();
      if( $headers[0] == 'nombre' &&
      $headers[1] == 'apellido_paterno' &&
      $headers[2] == 'apellido_materno' &&
      $headers[3] == 'correo' &&
      $headers[4] == 'titulo_docente' &&
      $headers[5] == 'carga_horaria' &&
      $headers[6] == 'nombre_cuenta' &&
      $headers[7] == 'telefono' &&
      $headers[8] == 'direccion' &&
      $headers[9] == 'perfil' &&
      $headers[10] == 'contrasena_cuenta' &&
      $headers[11] == 'ci' &&
      $headers[12] == 'cod_sis') {

        $valid = True;
      }
    });
    return $valid;
  }
}
