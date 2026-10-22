"""Genera un PDF de prueba de 7 páginas con ejercicios de técnica de mano izquierda
(material original para probar el digitalizador; no es una copia de ningún método)."""
import verovio, cairosvg, io
from pypdf import PdfWriter, PdfReader

PASOS = ['C','D','E','F','G','A','B']

def nota(step, alter, octave, tipo, dur):
    a = f'<alter>{alter}</alter>' if alter else ''
    return f'<note><pitch><step>{step}</step>{a}<octave>{octave}</octave></pitch><duration>{dur}</duration><type>{tipo}</type></note>'

def compases(notas, fifths, beats=4, final=True):
    # notas: lista de (step, alter, octave) en corcheas (divisions=2 -> corchea=1)
    out = []
    por = beats * 2
    for i in range(0, len(notas), por):
        grupo = notas[i:i+por]
        attrs = ''
        if i == 0:
            attrs = f'<attributes><divisions>2</divisions><key><fifths>{fifths}</fifths></key><time><beats>{beats}</beats><beat-type>4</beat-type></time><clef><sign>G</sign><line>2</line></clef></attributes>'
        cuerpo = ''.join(nota(s, a, o, 'eighth', 1) for s, a, o in grupo)
        ultimo = i + por >= len(notas)
        barra = '<barline location="right"><bar-style>light-heavy</bar-style></barline>' if (ultimo and final) else ''
        out.append(f'<measure number="{i//por+1}">{attrs}{cuerpo}{barra}</measure>')
    return ''.join(out)

def musicxml(titulo, ejercicios):
    """ejercicios: lista de (nombre, fifths, notas). Cada ejercicio va como parte de una misma voz con doble barra final."""
    medidas = []
    n = 1
    for nombre, fifths, notas in ejercicios:
        xml = compases(notas, fifths)
        # renumerar compases y poner el nombre del ejercicio como texto
        import re
        bloques = re.findall(r'<measure number="\d+">(.*?)</measure>', xml, re.S)
        for j, b in enumerate(bloques):
            dirn = f'<direction placement="above"><direction-type><words font-weight="bold">{nombre}</words></direction-type></direction>' if j == 0 else ''
            salto = '<print new-system="yes"/>' if (j == 0 and n > 1) else ''
            medidas.append(f'<measure number="{n}">{salto}{dirn}{b}</measure>')
            n += 1
    return f'''<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE score-partwise PUBLIC "-//Recordare//DTD MusicXML 3.1 Partwise//EN" "http://www.musicxml.org/dtds/partwise.dtd">
<score-partwise version="3.1"><work><work-title>{titulo}</work-title></work>
<part-list><score-part id="P1"><part-name>Violin</part-name></score-part></part-list>
<part id="P1">{''.join(medidas)}</part></score-partwise>'''

LA = [('A',0,4),('B',0,4),('C',1,5),('D',0,5)]          # cuerda La, patrón 2 alto
MI = [('E',0,5),('F',1,5),('G',1,5),('A',0,5)]          # cuerda Mi, patrón 2 alto
RE = [('D',0,4),('E',0,4),('F',1,4),('G',0,4)]          # cuerda Re
def patron(cuerda, dedos, veces): return [cuerda[d] for d in dedos] * veces

paginas_musica = {
  4: ('Ejercicios de dedos - pagina 4', [
      ('N.o 1', 3, patron(LA, [1,2,1,2], 4) + patron(LA, [2,3,2,3], 4)),
      ('N.o 2', 3, patron(LA, [0,1,2,3], 4) + patron(LA, [3,2,1,0], 4)),
  ]),
  6: ('Ejercicios de dedos - pagina 6', [
      ('N.o 5', 3, patron(MI, [1,2,3,2], 4) + patron(RE, [1,2,3,2], 4)),
      ('N.o 6', 3, patron(MI, [0,2,1,3], 4) + patron(MI, [3,1,2,0], 4)),
  ]),
  2: ('Otra pagina', [('Escala', 0, [('C',0,4),('D',0,4),('E',0,4),('F',0,4),('G',0,4),('A',0,4),('B',0,4),('C',0,5)]*2)]),
}

tk = verovio.toolkit()
tk.setOptions({'pageWidth': 2100, 'pageHeight': 2970, 'scale': 75, 'adjustPageHeight': False, 'header': 'none', 'footer': 'none'})
escritor = PdfWriter()
for p in range(1, 8):
    if p in paginas_musica:
        titulo, ej = paginas_musica[p]
        tk.loadData(musicxml(titulo, ej))
        svg = tk.renderToSVG(1)
    else:
        texto = 'Metodo de prueba MSEA' if p == 1 else f'Pagina {p}: texto de introduccion, sin musica.'
        svg = f'<svg xmlns="http://www.w3.org/2000/svg" width="2100" height="2970" viewBox="0 0 2100 2970"><rect width="100%" height="100%" fill="white"/><text x="200" y="400" font-size="90" font-family="serif">{texto}</text></svg>'
    pdf = cairosvg.svg2pdf(bytestring=svg.encode(), output_width=595, output_height=842, background_color='white')
    escritor.add_page(PdfReader(io.BytesIO(pdf)).pages[0])
    # número de página visible
with open('tests/e2e/metodo-prueba.pdf', 'wb') as f:
    escritor.write(f)
print('ok')
