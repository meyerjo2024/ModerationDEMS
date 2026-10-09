/** What a value looks like after crossing the server → client boundary (Dates become strings). */
export type Jsonify<T> = T extends Date
  ? string
  : T extends (infer U)[]
    ? Jsonify<U>[]
    : T extends object
      ? { [K in keyof T]: Jsonify<T[K]> }
      : T;

export const toJson = <T,>(v: T): Jsonify<T> => JSON.parse(JSON.stringify(v));
