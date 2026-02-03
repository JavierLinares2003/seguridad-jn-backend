// Sistema de Gestión de Personal y Proyectos
// Diagrama ER para dbDiagram.io
// Pegar este código en: https://dbdiagram.io/d

// ============================================
// MÓDULO DE AUTENTICACIÓN
// ============================================

Table users {
  id bigserial [pk, increment]
  name varchar(255) [not null]
  email varchar(255) [unique, not null]
  email_verified_at timestamp
  password varchar(255) [not null]
  remember_token varchar(100)
  estado varchar(20) [default: 'activo', note: 'activo, inactivo, bloqueado']
  ultimo_login timestamp
  created_at timestamp [default: `CURRENT_TIMESTAMP`]
  updated_at timestamp [default: `CURRENT_TIMESTAMP`]
  
  indexes {
    email
  }
}

Table roles {
  id bigserial [pk, increment]
  name varchar(255) [unique, not null]
  guard_name varchar(255) [not null]
  created_at timestamp
  updated_at timestamp
}

Table permissions {
  id bigserial [pk, increment]
  name varchar(255) [unique, not null]
  guard_name varchar(255) [not null]
  created_at timestamp
  updated_at timestamp
}

Table model_has_roles {
  role_id bigint [ref: > roles.id]
  model_type varchar(255)
  model_id bigint
  
  indexes {
    (model_id, model_type)
  }
}

Table role_has_permissions {
  permission_id bigint [ref: > permissions.id]
  role_id bigint [ref: > roles.id]
}

// ============================================
// CATÁLOGOS GENERALES
// ============================================

Table estados_civiles {
  id bigserial [pk, increment]
  nombre varchar(50) [unique, not null]
  activo boolean [default: true]
  created_at timestamp
  updated_at timestamp
  
  Note: 'Soltero, Casado, Divorciado, Viudo, Unión de Hecho'
}

Table tipos_sangre {
  id bigserial [pk, increment]
  nombre varchar(10) [unique, not null]
  activo boolean [default: true]
  created_at timestamp
  updated_at timestamp
  
  Note: 'A+, A-, B+, B-, AB+, AB-, O+, O-'
}

Table tipos_contratacion {
  id bigserial [pk, increment]
  nombre varchar(50) [unique, not null]
  activo boolean [default: true]
  created_at timestamp
  updated_at timestamp
  
  Note: 'Indefinido, Plazo Fijo, Por Proyecto'
}

Table tipos_pago {
  id bigserial [pk, increment]
  nombre varchar(50) [unique, not null]
  activo boolean [default: true]
  created_at timestamp
  updated_at timestamp
  
  Note: 'Efectivo, Transferencia, Cheque'
}

Table sexos {
  id bigserial [pk, increment]
  nombre varchar(20) [unique, not null]
  activo boolean [default: true]
  created_at timestamp
  updated_at timestamp
  
  Note: 'Masculino, Femenino, Otro'
}

Table departamentos {
  id bigserial [pk, increment]
  nombre varchar(100) [unique, not null]
  activo boolean [default: true]
  created_at timestamp
  updated_at timestamp
  
  Note: 'Departamentos de la empresa: Administrativo, Operaciones, RRHH, etc.'
}

Table departamentos_geograficos {
  id bigserial [pk, increment]
  codigo varchar(2) [unique, not null]
  nombre varchar(100) [unique, not null]
  activo boolean [default: true]
  created_at timestamp
  updated_at timestamp
  
  Note: '22 departamentos de Guatemala'
}

Table municipios {
  id bigserial [pk, increment]
  departamento_geo_id bigint [ref: > departamentos_geograficos.id]
  codigo varchar(4) [unique, not null]
  nombre varchar(100) [not null]
  activo boolean [default: true]
  created_at timestamp
  updated_at timestamp
  
  Note: '340 municipios de Guatemala'
}

Table catalogo_parentescos {
  id bigserial [pk, increment]
  nombre varchar(50) [unique, not null]
  activo boolean [default: true]
  
  Note: 'Padre, Madre, Esposo/a, Hijo/a, etc.'
}

Table catalogo_redes_sociales {
  id bigserial [pk, increment]
  nombre varchar(50) [unique, not null]
  icono varchar(50)
  url_base varchar(255)
  activo boolean [default: true]
  
  Note: 'Facebook, Instagram, Twitter, LinkedIn, etc.'
}

Table tipos_documentos_personal {
  id bigserial [pk, increment]
  nombre varchar(100) [unique, not null]
  requiere_vencimiento boolean [default: false]
  extensiones_permitidas text[]
  activo boolean [default: true]
  
  Note: 'DPI, Antecedentes, CV, Carnet IGSS, etc.'
}

Table niveles_estudio {
  id bigserial [pk, increment]
  nombre varchar(50) [unique, not null]
  orden int [unique, not null]
  activo boolean [default: true]
  
  Note: 'Primaria, Básicos, Diversificado, Universidad'
}

Table tipos_personal {
  id bigserial [pk, increment]
  nombre varchar(50) [unique, not null]
  activo boolean [default: true]
  
  Note: 'Operativo, Administrativo, Supervisor'
}

Table turnos {
  id bigserial [pk, increment]
  nombre varchar(50) [unique, not null]
  hora_inicio time [not null]
  hora_fin time [not null]
  horas_trabajo decimal(4,2) [not null]
  descripcion text
  requiere_descanso boolean [default: false]
  activo boolean [default: true]
  
  Note: 'Diurno 8h, Nocturno 8h, 24h, 12h día, 12h noche'
}

// ============================================
// MÓDULO DE PERSONAL
// ============================================

Table personal {
  id bigserial [pk, increment]
  nombres varchar(100) [not null]
  apellidos varchar(100) [not null]
  dpi varchar(13) [unique, not null]
  nit varchar(15) [unique]
  email varchar(150) [unique, not null]
  telefono varchar(15) [not null]
  numero_iggs varchar(50)
  fecha_nacimiento date [not null]
  estado_civil_id bigint [ref: > estados_civiles.id]
  altura decimal(5,2) [not null, note: 'En metros']
  tipo_sangre_id bigint [ref: > tipos_sangre.id]
  peso decimal(5,2) [not null, note: 'En libras']
  sabe_leer boolean [default: true]
  sabe_escribir boolean [default: true]
  es_alergico boolean [default: false]
  alergias text
  tipo_contratacion_id bigint [ref: > tipos_contratacion.id]
  salario_base decimal(10,2) [not null]
  tipo_pago_id bigint [ref: > tipos_pago.id]
  puesto varchar(100) [not null]
  sexo_id bigint [ref: > sexos.id]
  departamento_id bigint [ref: > departamentos.id]
  observaciones text
  foto_perfil varchar(255)
  estado varchar(20) [default: 'activo', note: 'activo, inactivo, suspendido']
  created_at timestamp [default: `CURRENT_TIMESTAMP`]
  updated_at timestamp [default: `CURRENT_TIMESTAMP`]
  deleted_at timestamp
  
  indexes {
    dpi
    estado
    deleted_at
  }
}

Table personal_direcciones {
  id bigserial [pk, increment]
  personal_id bigint [unique, ref: - personal.id]
  departamento_geo_id bigint [ref: > departamentos_geograficos.id]
  municipio_id bigint [ref: > municipios.id]
  zona int [note: '1-25']
  direccion_completa text [not null]
  es_direccion_actual boolean [default: true]
  created_at timestamp
  updated_at timestamp
  
  indexes {
    personal_id
  }
}

Table personal_referencias_laborales {
  id bigserial [pk, increment]
  personal_id bigint [ref: > personal.id]
  nombre_empresa varchar(200) [not null]
  puesto_ocupado varchar(100) [not null]
  telefono varchar(15) [not null]
  direccion text
  fecha_inicio date [not null]
  fecha_fin date
  motivo_retiro text
  created_at timestamp
  updated_at timestamp
  
  indexes {
    personal_id
  }
  
  Note: 'Historial laboral del personal'
}

Table personal_redes_sociales {
  id bigserial [pk, increment]
  personal_id bigint [ref: > personal.id]
  red_social_id bigint [ref: > catalogo_redes_sociales.id]
  nombre_usuario varchar(100) [not null]
  url_perfil varchar(255)
  created_at timestamp
  updated_at timestamp
  
  indexes {
    personal_id
    (personal_id, red_social_id) [unique]
  }
}

Table personal_familiares {
  id bigserial [pk, increment]
  personal_id bigint [ref: > personal.id]
  parentesco_id bigint [ref: > catalogo_parentescos.id]
  nombre_completo varchar(200) [not null]
  telefono varchar(15) [not null]
  es_contacto_emergencia boolean [default: false]
  created_at timestamp
  updated_at timestamp
  
  indexes {
    personal_id
  }
}

Table personal_documentos {
  id bigserial [pk, increment]
  personal_id bigint [ref: > personal.id]
  tipo_documento_personal_id bigint [ref: > tipos_documentos_personal.id]
  nombre_documento varchar(255) [not null]
  descripcion text
  ruta_archivo varchar(500) [unique, not null]
  nombre_archivo_original varchar(255) [not null]
  extension varchar(10) [not null]
  tamanio_kb int [not null]
  fecha_vencimiento date
  fecha_subida timestamp [default: `CURRENT_TIMESTAMP`]
  subido_por_user_id bigint [ref: > users.id]
  estado_documento varchar(20) [default: 'vigente', note: 'vigente, por_vencer, vencido']
  dias_alerta_vencimiento int [default: 30]
  created_at timestamp
  updated_at timestamp
  deleted_at timestamp
  
  indexes {
    personal_id
    fecha_vencimiento
    estado_documento
  }
}

// ============================================
// MÓDULO DE PROYECTOS
// ============================================

Table tipos_proyecto {
  id bigserial [pk, increment]
  nombre varchar(100) [unique, not null]
  prefijo_correlativo varchar(10) [unique, not null]
  descripcion text
  activo boolean [default: true]
  
  Note: 'Seguridad (SEG), Limpieza (LMP), etc.'
}

Table proyectos {
  id bigserial [pk, increment]
  tipo_proyecto_id bigint [ref: > tipos_proyecto.id]
  correlativo varchar(50) [unique, not null, note: 'SEG-2024-0001']
  nombre_proyecto varchar(255) [not null]
  descripcion text
  empresa_cliente varchar(200) [not null]
  estado_proyecto varchar(20) [default: 'planificacion', note: 'planificacion, activo, suspendido, finalizado']
  fecha_inicio_estimada date
  fecha_fin_estimada date
  fecha_inicio_real date
  fecha_fin_real date
  created_at timestamp [default: `CURRENT_TIMESTAMP`]
  updated_at timestamp [default: `CURRENT_TIMESTAMP`]
  deleted_at timestamp
  
  indexes {
    correlativo
    estado_proyecto
    deleted_at
  }
}

Table proyectos_ubicaciones {
  id bigserial [pk, increment]
  proyecto_id bigint [unique, ref: - proyectos.id]
  departamento_geo_id bigint [ref: > departamentos_geograficos.id]
  municipio_id bigint [ref: > municipios.id]
  zona int
  direccion_completa text [not null]
  coordenadas_gps point
  created_at timestamp
  updated_at timestamp
  
  indexes {
    proyecto_id
  }
}

Table tipos_documentos_facturacion {
  id bigserial [pk, increment]
  nombre varchar(50) [unique, not null]
  activo boolean [default: true]
  
  Note: 'Factura, Recibo, Nota de Débito, Nota de Crédito'
}

Table periodicidades_pago {
  id bigserial [pk, increment]
  nombre varchar(50) [unique, not null]
  dias int [not null]
  activo boolean [default: true]
  
  Note: 'Semanal (7), Quincenal (15), Mensual (30)'
}

Table proyectos_facturacion {
  id bigserial [pk, increment]
  proyecto_id bigint [unique, ref: - proyectos.id]
  tipo_documento_facturacion_id bigint [ref: > tipos_documentos_facturacion.id]
  nit_cliente varchar(15) [not null]
  nombre_facturacion varchar(255) [not null]
  direccion_facturacion text [not null]
  forma_pago varchar(100) [not null]
  periodicidad_pago_id bigint [ref: > periodicidades_pago.id]
  dia_pago int [note: '1-31']
  monto_proyecto_total decimal(12,2)
  moneda varchar(3) [default: 'GTQ']
  created_at timestamp
  updated_at timestamp
  
  indexes {
    proyecto_id
  }
}

Table proyectos_contactos {
  id bigserial [pk, increment]
  proyecto_id bigint [ref: > proyectos.id]
  nombre_contacto varchar(200) [not null]
  telefono varchar(15) [not null]
  email varchar(150)
  puesto varchar(100) [not null]
  es_contacto_principal boolean [default: false]
  created_at timestamp
  updated_at timestamp
  
  indexes {
    proyecto_id
    (proyecto_id, es_contacto_principal) [unique, note: 'Solo un contacto principal']
  }
}

Table proyectos_inventario {
  id bigserial [pk, increment]
  proyecto_id bigint [ref: > proyectos.id]
  codigo_inventario varchar(50) [not null]
  nombre_item varchar(200) [not null]
  cantidad_asignada int [not null]
  estado_item varchar(20) [default: 'asignado', note: 'asignado, en_uso, devuelto, dañado']
  fecha_asignacion date [default: `CURRENT_DATE`]
  fecha_devolucion date
  observaciones text
  created_at timestamp
  updated_at timestamp
  
  indexes {
    proyecto_id
    codigo_inventario
    (proyecto_id, codigo_inventario) [unique]
  }
}

Table proyectos_configuracion_personal {
  id bigserial [pk, increment]
  proyecto_id bigint [ref: > proyectos.id]
  nombre_puesto varchar(100) [not null]
  cantidad_requerida int [not null]
  edad_minima int [not null]
  edad_maxima int [not null]
  sexo_id bigint [ref: > sexos.id, note: 'NULL = indistinto']
  altura_minima decimal(5,2) [note: 'En metros']
  estudio_minimo_id bigint [ref: > niveles_estudio.id]
  tipo_personal_id bigint [ref: > tipos_personal.id]
  turno_id bigint [ref: > turnos.id]
  costo_hora_proyecto decimal(10,2) [not null, note: 'Lo que cobra la empresa']
  pago_hora_personal decimal(10,2) [not null, note: 'Lo que se le paga al empleado']
  margen_utilidad decimal(5,2) [note: 'Calculado automáticamente']
  estado varchar(20) [default: 'activo']
  created_at timestamp
  updated_at timestamp
  
  indexes {
    proyecto_id
  }
  
  Note: 'Configuración de puestos/roles para el proyecto'
}

Table tipos_documentos_proyecto {
  id bigserial [pk, increment]
  nombre varchar(100) [unique, not null]
  requiere_vencimiento boolean [default: false]
  extensiones_permitidas text[]
  activo boolean [default: true]
  
  Note: 'Contrato, Póliza, Planos, Licencias, etc.'
}

Table proyectos_documentos {
  id bigserial [pk, increment]
  proyecto_id bigint [ref: > proyectos.id]
  tipo_documento_proyecto_id bigint [ref: > tipos_documentos_proyecto.id]
  nombre_documento varchar(255) [not null]
  descripcion text
  ruta_archivo varchar(500) [unique, not null]
  nombre_archivo_original varchar(255) [not null]
  extension varchar(10) [not null]
  tamanio_kb int [not null]
  fecha_vencimiento date
  fecha_subida timestamp [default: `CURRENT_TIMESTAMP`]
  subido_por_user_id bigint [ref: > users.id]
  estado_documento varchar(20) [default: 'vigente']
  dias_alerta_vencimiento int [default: 30]
  created_at timestamp
  updated_at timestamp
  deleted_at timestamp
  
  indexes {
    proyecto_id
    fecha_vencimiento
  }
}

// ============================================
// MÓDULO DE OPERACIONES
// ============================================

Table operaciones_personal_asignado {
  id bigserial [pk, increment]
  personal_id bigint [ref: > personal.id]
  proyecto_id bigint [ref: > proyectos.id, note: 'nullable']
  configuracion_puesto_id bigint [ref: > proyectos_configuracion_personal.id, note: 'nullable']
  turno_id bigint [ref: > turnos.id]
  fecha_inicio date [not null]
  fecha_fin date
  estado_asignacion varchar(20) [default: 'activa', note: 'activa, finalizada, suspendida']
  motivo_suspension text
  notas text
  created_at timestamp
  updated_at timestamp
  
  indexes {
    personal_id
    proyecto_id
    (fecha_inicio, fecha_fin)
    estado_asignacion
  }
  
  Note: 'Asignación de personal a proyectos - NO puede haber solapamiento de fechas'
}

Table operaciones_asistencia {
  id bigserial [pk, increment]
  personal_asignado_id bigint [ref: > operaciones_personal_asignado.id]
  fecha_asistencia date [not null]
  hora_entrada time
  hora_salida time
  llego_tarde boolean [default: false]
  minutos_retraso int [default: 0]
  es_descanso boolean [default: false]
  fue_reemplazado boolean [default: false]
  personal_reemplazo_id bigint [ref: > personal.id]
  motivo_reemplazo text
  observaciones text
  registrado_por_user_id bigint [ref: > users.id]
  created_at timestamp
  updated_at timestamp
  
  indexes {
    personal_asignado_id
    fecha_asistencia
    personal_reemplazo_id
    (personal_asignado_id, fecha_asistencia) [unique]
  }
  
  Note: 'Registro diario de asistencia'
}

Table operaciones_prestamos {
  id bigserial [pk, increment]
  personal_id bigint [ref: > personal.id]
  monto_total decimal(10,2) [not null]
  saldo_pendiente decimal(10,2) [not null]
  tasa_interes decimal(5,2) [default: 0]
  fecha_prestamo date [default: `CURRENT_DATE`]
  fecha_primer_pago date
  cuotas_totales int
  cuotas_pagadas int [default: 0]
  monto_cuota decimal(10,2)
  estado_prestamo varchar(20) [default: 'activo', note: 'activo, pagado, cancelado']
  observaciones text
  aprobado_por_user_id bigint [ref: > users.id]
  created_at timestamp
  updated_at timestamp
  
  indexes {
    personal_id
    estado_prestamo
  }
}

Table operaciones_transacciones {
  id bigserial [pk, increment]
  personal_id bigint [ref: > personal.id]
  asistencia_id bigint [ref: > operaciones_asistencia.id]
  tipo_transaccion varchar(30) [not null, note: 'multa, uniforme, anticipo, prestamo, abono_prestamo, antecedentes, otro_descuento']
  monto decimal(10,2) [not null]
  descripcion text [not null]
  fecha_transaccion date [default: `CURRENT_DATE`]
  es_descuento boolean [default: true, note: 'TRUE=descuento, FALSE=abono']
  estado_transaccion varchar(20) [default: 'pendiente', note: 'pendiente, aplicado, cancelado']
  prestamo_id bigint [ref: > operaciones_prestamos.id]
  registrado_por_user_id bigint [ref: > users.id]
  created_at timestamp
  updated_at timestamp
  
  indexes {
    personal_id
    asistencia_id
    tipo_transaccion
    fecha_transaccion
  }
  
  Note: 'Multas, uniformes, anticipos, préstamos, descuentos'
}

// ============================================
// MÓDULO DE PLANILLA
// ============================================

Table planillas {
  id bigserial [pk, increment]
  nombre_planilla varchar(255) [not null]
  periodo_inicio date [not null]
  periodo_fin date [not null]
  fecha_creacion date [default: `CURRENT_DATE`]
  estado_planilla varchar(20) [default: 'borrador', note: 'borrador, revisión, aprobada, pagada, cancelada']
  total_bruto decimal(12,2) [default: 0]
  total_descuentos decimal(12,2) [default: 0]
  total_neto decimal(12,2) [default: 0]
  observaciones text
  creado_por_user_id bigint [ref: > users.id]
  aprobado_por_user_id bigint [ref: > users.id]
  fecha_aprobacion timestamp
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (periodo_inicio, periodo_fin)
    estado_planilla
  }
}

Table planillas_detalle {
  id bigserial [pk, increment]
  planilla_id bigint [ref: > planillas.id]
  personal_id bigint [ref: > personal.id]
  dias_trabajados int [default: 0]
  dias_descanso int [default: 0]
  horas_trabajadas decimal(10,2) [default: 0]
  salario_base_diario decimal(10,2)
  salario_proyectos decimal(10,2) [default: 0]
  total_ingresos decimal(10,2) [default: 0]
  total_multas decimal(10,2) [default: 0]
  total_uniformes decimal(10,2) [default: 0]
  total_anticipos decimal(10,2) [default: 0]
  total_prestamos decimal(10,2) [default: 0]
  total_otros_descuentos decimal(10,2) [default: 0]
  total_descuentos decimal(10,2) [default: 0]
  total_neto decimal(10,2) [default: 0]
  observaciones text
  created_at timestamp
  updated_at timestamp
  
  indexes {
    planilla_id
    personal_id
    (planilla_id, personal_id) [unique]
  }
}

// ============================================
// RELACIONES PRINCIPALES
// ============================================

// Personal
Ref: personal.estado_civil_id > estados_civiles.id
Ref: personal.tipo_sangre_id > tipos_sangre.id
Ref: personal.tipo_contratacion_id > tipos_contratacion.id
Ref: personal.tipo_pago_id > tipos_pago.id
Ref: personal.sexo_id > sexos.id
Ref: personal.departamento_id > departamentos.id

// Proyectos
Ref: proyectos.tipo_proyecto_id > tipos_proyecto.id

// Operaciones
Ref: operaciones_personal_asignado.personal_id > personal.id
Ref: operaciones_personal_asignado.proyecto_id > proyectos.id
Ref: operaciones_personal_asignado.configuracion_puesto_id > proyectos_configuracion_personal.id
Ref: operaciones_personal_asignado.turno_id > turnos.id

// Planilla
Ref: planillas_detalle.planilla_id > planillas.id
Ref: planillas_detalle.personal_id > personal.id